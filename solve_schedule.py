#!/usr/bin/env python3
"""
Solve the integration schedule conflict problem.

14 integrations, each fires multiple times per day.
Constraint: any two integrations' times must be >= 10 minutes apart.
Each integration has a frequency (every Nh) and gets assigned specific hours + minute offset.
"""

def check_conflicts(assignments, min_gap=10):
    """
    assignments: dict of {integration_id: list of minute-of-day values}
    Returns list of conflict tuples.
    """
    conflicts = []
    ids = list(assignments.keys())
    for i in range(len(ids)):
        for j in range(i+1, len(ids)):
            id_a, id_b = ids[i], ids[j]
            for ta in assignments[id_a]:
                for tb in assignments[id_b]:
                    diff = abs(ta - tb)
                    circ = min(diff, 1440 - diff)
                    if circ < min_gap:
                        conflicts.append((id_a, id_b, ta, tb, circ))
    return conflicts


def hours_to_times(hours, minute):
    return [h * 60 + minute for h in hours]


def format_time(mod):
    return f"{mod // 60:02d}:{mod % 60:02d}"


def time_list_str(times):
    return ",".join(format_time(t) for t in sorted(times))


# ============================================================
# APPROACH: 3-hour rotation, ALL integrations sync every 3 hours
# ============================================================
# Group 0: hours 0,3,6,9,12,15,18,21  (8 times/day)
# Group 1: hours 1,4,7,10,13,16,19,22 (8 times/day)
# Group 2: hours 2,5,8,11,14,17,20,23 (8 times/day)
#
# Within each group: offsets :00,:10,:20,:30,:40 (max 5 per group)
# Cross-group: :40 -> next hour :00 = 20 min gap. Safe!
#
# 5+5+4 = 14 integrations. PERFECT!

print("=" * 70)
print("APPROACH: 3-hour rotation (every 3h for all)")
print("=" * 70)

group0_hours = list(range(0, 24, 3))   # 0,3,6,9,12,15,18,21
group1_hours = list(range(1, 24, 3))   # 1,4,7,10,13,16,19,22
group2_hours = list(range(2, 24, 3))   # 2,5,8,11,14,17,20,23

assignments_3h = {
    # Group 0: 5 integrations at hours 0,3,6,9,12,15,18,21
    19: hours_to_times(group0_hours, 0),    # ทัวร์น้ำดี :00
    2:  hours_to_times(group0_hours, 10),   # วีอาร์เวิลด์ :10
    3:  hours_to_times(group0_hours, 20),   # ทัวร์แฟคทอรี่ :20
    5:  hours_to_times(group0_hours, 30),   # เช็คอิน :30
    6:  hours_to_times(group0_hours, 40),   # โก365 :40

    # Group 1: 5 integrations at hours 1,4,7,10,13,16,19,22
    1:  hours_to_times(group1_hours, 0),    # ซีโก้ :00
    20: hours_to_times(group1_hours, 10),   # ไอทราเวล :10
    21: hours_to_times(group1_hours, 20),   # ทีทีเอ็น :20
    17: hours_to_times(group1_hours, 30),   # ว้าวเจอร์นี่ :30
    11: hours_to_times(group1_hours, 40),   # คิวอีบุ๊คกิ้ง :40

    # Group 2: 4 integrations at hours 2,5,8,11,14,17,20,23
    15: hours_to_times(group2_hours, 0),    # ทัวร์เมกเกอร์ :00
    13: hours_to_times(group2_hours, 10),   # ลุกซ์แพลนเนท :10
    14: hours_to_times(group2_hours, 20),   # มีทูทัวร์ :20
    22: hours_to_times(group2_hours, 30),   # ไทยเที่ยวนอก :30
}

conflicts = check_conflicts(assignments_3h)
print(f"\nConflicts: {len(conflicts)}")
for c in conflicts:
    print(f"  #{c[0]} ({format_time(c[2])}) <-> #{c[1]} ({format_time(c[3])}) = {c[4]} min gap")

if not conflicts:
    print("\n✅ ZERO CONFLICTS!")

# Print the schedule
print("\n" + "-" * 70)
print("SCHEDULE:")
print("-" * 70)

names = {
    1: "ซีโก้", 2: "วีอาร์เวิลด์", 3: "ทัวร์แฟคทอรี่",
    5: "เช็คอิน", 6: "โก365", 11: "คิวอีบุ๊คกิ้ง",
    13: "ลุกซ์แพลนเนท", 14: "มีทูทัวร์", 15: "ทัวร์เมกเกอร์",
    17: "ว้าวเจอร์นี่", 19: "ทัวร์น้ำดี", 20: "ไอทราเวล",
    21: "ทีทีเอ็น", 22: "ไทยเที่ยวนอก"
}

for iid, times in sorted(assignments_3h.items()):
    tl = time_list_str(times)
    print(f"  #{iid:2d} {names[iid]:20s}: {tl}")
    print(f"      ({len(times)} times/day, len={len(tl)} chars)")

# Check max string length (sync_schedule max:100)
print("\n" + "-" * 70)
print("STRING LENGTH CHECK (max 100 chars):")
for iid, times in sorted(assignments_3h.items()):
    tl = time_list_str(times)
    status = "OK" if len(tl) <= 100 else "TOO LONG!"
    print(f"  #{iid:2d}: {len(tl):3d} chars - {status}")


# ============================================================
# But wait - some integrations don't need 8x/day (every 3h).
# #14 originally every 6h, #22 originally daily, #13 every 4h
# With every-3h they get MORE frequent. That's fine (more sync = better data).
# But #22 (ไทยเที่ยวนอก) going from 1x/day to 8x/day seems excessive.
#
# Option: reduce #22 to fewer times but still at :30 in Group 2 hours.
# e.g., just 02:30,14:30 (2x/day) or even just 02:30 (1x/day)
# This still works because :30 doesn't conflict with anyone.
# ============================================================

print("\n" + "=" * 70)
print("OPTIMIZED: Reduce low-priority frequencies")
print("=" * 70)

assignments_opt = dict(assignments_3h)  # copy
# Keep #22 at just 2x/day (still group 2, :30)
assignments_opt[22] = hours_to_times([2, 14], 30)  # 02:30, 14:30
# Keep #14 at 4x/day (every 6h within group 2): hours 2,8,14,20
assignments_opt[14] = hours_to_times([2, 8, 14, 20], 20)  # every 6h
# Keep #13 at 4x/day (every 6h within group 2): hours 5,11,17,23
assignments_opt[13] = hours_to_times([5, 11, 17, 23], 10)  # every 6h

conflicts2 = check_conflicts(assignments_opt)
print(f"\nConflicts: {len(conflicts2)}")
for c in conflicts2:
    print(f"  #{c[0]} ({format_time(c[2])}) <-> #{c[1]} ({format_time(c[3])}) = {c[4]} min gap")

if not conflicts2:
    print("\n✅ ZERO CONFLICTS!")

print("\n" + "-" * 70)
print("OPTIMIZED SCHEDULE:")
print("-" * 70)
for iid, times in sorted(assignments_opt.items()):
    tl = time_list_str(times)
    print(f"  #{iid:2d} {names[iid]:20s}: {tl}")
    print(f"      ({len(times)} times/day, len={len(tl)} chars)")


# ============================================================
# Timeline verification: print first 6 hours
# ============================================================
print("\n" + "=" * 70)
print("TIMELINE (00:00 - 05:59):")
print("=" * 70)

all_events = []
for iid, times in assignments_opt.items():
    for t in times:
        if t < 360:  # first 6 hours
            all_events.append((t, iid))

all_events.sort()
prev = -100
for t, iid in all_events:
    gap = t - prev if prev >= 0 else 999
    marker = " ⚠" if gap < 10 else ""
    print(f"  {format_time(t)}  #{iid:2d} {names[iid]:20s}  (gap={gap:3d} min){marker}")
    prev = t
