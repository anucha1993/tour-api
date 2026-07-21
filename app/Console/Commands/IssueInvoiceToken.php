<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class IssueInvoiceToken extends Command
{
    protected $signature = 'invoice:issue-token
                            {--email=invoice-service@nexttrip.local : Service account email}
                            {--name=Invoice Service : Service account display name}
                            {--fresh : Revoke existing invoice-service tokens before issuing a new one}';

    protected $description = 'Issue a Sanctum API token for the nexttrip-invoice service account (used by the invoice app to pull master/tour data)';

    public function handle(): int
    {
        $email = (string) $this->option('email');
        $name = (string) $this->option('name');

        // Service account: login is by token only, so the password is random
        // and discarded. Role "it" keeps it out of the sales dropdown.
        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Str::random(48),
                'role' => 'it',
                'is_active' => true,
                'is_sales' => false,
            ]
        );

        if (! $user->is_active) {
            $user->update(['is_active' => true]);
        }

        if ($this->option('fresh')) {
            $revoked = $user->tokens()->where('name', 'invoice-service')->delete();
            $this->warn("Revoked {$revoked} existing 'invoice-service' token(s).");
        }

        $token = $user->createToken('invoice-service')->plainTextToken;

        $this->newLine();
        $this->info('✔ Invoice service token issued.');
        $this->line("  User: {$user->name} <{$user->email}> (id={$user->id}, role={$user->role})");
        $this->newLine();
        $this->line('Add this line to nexttrip-invoice/.env :');
        $this->newLine();
        $this->line('  TOUR_API_TOKEN=' . $token);
        $this->newLine();
        $this->warn('The token is shown only once — copy it now.');

        return 0;
    }
}
