<?php

namespace App\Services\WholesalerAdapters\Mapper;

use App\Models\SectionDefinition;
use App\Models\WholesalerFieldMapping;
use Illuminate\Support\Facades\Log;

/**
 * Validates and converts data types according to section definitions
 */
class TypeValidator
{
    /**
     * Validate and convert a value to the expected type
     */
    public function validate(mixed $value, SectionDefinition $definition): ValidationResult
    {
        $type = $definition->data_type;
        
        // Handle null values
        if ($value === null || $value === '') {
            if ($definition->is_required && $definition->default_value === null) {
                return ValidationResult::error("Required field is empty");
            }
            return ValidationResult::success($definition->default_value);
        }

        try {
            $converted = match ($type) {
                'TEXT' => $this->validateText($value),
                'INT' => $this->validateInt($value),
                'DECIMAL' => $this->validateDecimal($value),
                'DATE' => $this->validateDate($value),
                'DATETIME' => $this->validateDateTime($value),
                'BOOLEAN' => $this->validateBoolean($value),
                'ENUM' => $this->validateEnum($value, $definition->enum_values ?? []),
                'ARRAY_TEXT' => $this->validateArrayText($value),
                'ARRAY_INT' => $this->validateArrayInt($value),
                'ARRAY_DECIMAL' => $this->validateArrayDecimal($value),
                'JSON' => $this->validateJson($value),
                default => throw new \InvalidArgumentException("Unknown type: $type"),
            };

            return ValidationResult::success($converted);
        } catch (\Exception $e) {
            return ValidationResult::error($e->getMessage(), $value, $type);
        }
    }

    /**
     * Validate TEXT
     */
    protected function validateText(mixed $value): string
    {
        if (is_array($value)) {
            return json_encode($value);
        }
        return (string) $value;
    }

    /**
     * Validate INT
     */
    protected function validateInt(mixed $value): int
    {
        if (is_numeric($value)) {
            return (int) $value;
        }
        
        // Try to extract number from string
        if (is_string($value)) {
            $cleaned = preg_replace('/[^0-9\-]/', '', $value);
            if (is_numeric($cleaned)) {
                return (int) $cleaned;
            }
        }
        
        throw new \InvalidArgumentException("Cannot convert to INT: " . json_encode($value));
    }

    /**
     * Validate DECIMAL
     */
    protected function validateDecimal(mixed $value): float
    {
        if (is_numeric($value)) {
            return round((float) $value, 2);
        }
        
        // Try to extract number from string (remove currency symbols, commas)
        if (is_string($value)) {
            $cleaned = preg_replace('/[^0-9.\-]/', '', str_replace(',', '', $value));
            if (is_numeric($cleaned)) {
                return round((float) $cleaned, 2);
            }
        }
        
        throw new \InvalidArgumentException("Cannot convert to DECIMAL: " . json_encode($value));
    }

    /**
     * Validate DATE
     */
    protected function validateDate(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        
        // Try common date formats
        $formats = ['Y-m-d', 'd/m/Y', 'm/d/Y', 'Y/m/d', 'd-m-Y', 'Y-m-d H:i:s'];
        
        foreach ($formats as $format) {
            $date = \DateTime::createFromFormat($format, $value);
            if ($date !== false) {
                return $date->format('Y-m-d');
            }
        }
        
        // Try strtotime
        $timestamp = strtotime($value);
        if ($timestamp !== false) {
            return date('Y-m-d', $timestamp);
        }
        
        throw new \InvalidArgumentException("Cannot parse DATE: $value");
    }

    /**
     * Validate DATETIME
     */
    protected function validateDateTime(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }
        
        $timestamp = strtotime($value);
        if ($timestamp !== false) {
            return date('Y-m-d H:i:s', $timestamp);
        }
        
        throw new \InvalidArgumentException("Cannot parse DATETIME: $value");
    }

    /**
     * Validate BOOLEAN
     */
    protected function validateBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        
        $truthy = ['true', '1', 'yes', 'on', 'active', 'enabled'];
        $falsy = ['false', '0', 'no', 'off', 'inactive', 'disabled', ''];
        
        $lower = strtolower((string) $value);
        
        if (in_array($lower, $truthy, true)) {
            return true;
        }
        
        if (in_array($lower, $falsy, true)) {
            return false;
        }
        
        throw new \InvalidArgumentException("Cannot convert to BOOLEAN: " . json_encode($value));
    }

    /**
     * Validate ENUM
     */
    protected function validateEnum(mixed $value, array $allowedValues): string
    {
        $stringValue = (string) $value;
        
        if (in_array($stringValue, $allowedValues, true)) {
            return $stringValue;
        }
        
        // Try case-insensitive match
        foreach ($allowedValues as $allowed) {
            if (strtolower($stringValue) === strtolower($allowed)) {
                return $allowed;
            }
        }
        
        throw new \InvalidArgumentException(
            "Invalid ENUM value '$stringValue'. Allowed: " . implode(', ', $allowedValues)
        );
    }

    /**
     * Validate ARRAY_TEXT
     */
    protected function validateArrayText(mixed $value): array
    {
        $array = $this->toArray($value);
        return array_map('strval', $array);
    }

    /**
     * Validate ARRAY_INT
     */
    protected function validateArrayInt(mixed $value): array
    {
        $array = $this->toArray($value);
        return array_map('intval', $array);
    }

    /**
     * Validate ARRAY_DECIMAL
     */
    protected function validateArrayDecimal(mixed $value): array
    {
        $array = $this->toArray($value);
        return array_map(fn($v) => round((float) $v, 2), $array);
    }

    /**
     * Convert value to array
     */
    protected function toArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        
        if (is_string($value)) {
            // Try JSON decode
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $decoded;
            }
            
            // Try splitting by common delimiters
            if (str_contains($value, ',')) {
                return array_map('trim', explode(',', $value));
            }
            if (str_contains($value, '|')) {
                return array_map('trim', explode('|', $value));
            }
            if (str_contains($value, ';')) {
                return array_map('trim', explode(';', $value));
            }
            
            // Single value
            return [$value];
        }
        
        return [$value];
    }

    /**
     * Validate JSON
     */
    protected function validateJson(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }
        
        throw new \InvalidArgumentException("Invalid JSON: " . json_encode($value));
    }
}

/**
 * Result of validation
 */
class ValidationResult
{
    public function __construct(
        public bool $success,
        public mixed $value = null,
        public ?string $error = null,
        public mixed $originalValue = null,
        public ?string $expectedType = null,
    ) {}

    public static function success(mixed $value): self
    {
        return new self(success: true, value: $value);
    }

    public static function error(string $message, mixed $originalValue = null, ?string $expectedType = null): self
    {
        return new self(
            success: false,
            error: $message,
            originalValue: $originalValue,
            expectedType: $expectedType,
        );
    }
}
