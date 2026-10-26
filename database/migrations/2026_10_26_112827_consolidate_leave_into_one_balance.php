<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Casual, sick, and earned leave become one balance. Existing requests stay, and they draw on that balance.
     */
    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('leave_types')) {
            return;
        }

        $now = now();
        $leaveId = DB::table('leave_types')->where('code', 'leave')->value('id');
        $others = DB::table('leave_types')->when($leaveId !== null, fn ($query) => $query->where('id', '!=', $leaveId))->get();
        $allowance = round((float) $others->where('is_paid', true)->sum('annual_allowance'), 1);

        if ($leaveId === null) {
            $leaveId = DB::table('leave_types')->insertGetId([
                'name' => 'Leave',
                'code' => 'leave',
                'is_paid' => true,
                'annual_allowance' => $allowance > 0 ? $allowance : 31,
                'needs_hr_approval' => true,
                'hr_threshold_days' => 3,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } elseif ($allowance > 0) {
            DB::table('leave_types')->where('id', $leaveId)->update([
                'name' => 'Leave',
                'is_paid' => true,
                'annual_allowance' => $allowance,
                'is_active' => true,
                'updated_at' => $now,
            ]);
        }

        $otherIds = $others->pluck('id');
        if ($otherIds->isEmpty()) {
            return;
        }

        $balances = DB::table('leave_balances')->whereIn('leave_type_id', $otherIds)->get();
        foreach ($balances->groupBy(fn (object $row): string => $row->user_id.'-'.$row->year) as $rows) {
            $first = $rows->first();
            $allocated = round((float) $rows->sum('allocated'), 1);
            $used = round((float) $rows->sum('used'), 1);
            $current = DB::table('leave_balances')
                ->where('user_id', $first->user_id)
                ->where('leave_type_id', $leaveId)
                ->where('year', $first->year)
                ->first();

            if ($current === null) {
                DB::table('leave_balances')->insert([
                    'user_id' => $first->user_id,
                    'leave_type_id' => $leaveId,
                    'year' => $first->year,
                    'allocated' => $allocated,
                    'used' => $used,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                DB::table('leave_balances')->where('id', $current->id)->update([
                    'allocated' => round((float) $current->allocated + $allocated, 1),
                    'used' => round((float) $current->used + $used, 1),
                    'updated_at' => $now,
                ]);
            }
        }

        DB::table('leave_balances')->whereIn('leave_type_id', $otherIds)->delete();
        DB::table('leave_requests')->whereIn('leave_type_id', $otherIds)->update([
            'leave_type_id' => $leaveId,
            'updated_at' => $now,
        ]);
        DB::table('leave_types')->whereIn('id', $otherIds)->delete();
    }

    /**
     * The separate casual, sick, and earned balances cannot be restored from the combined total.
     */
    public function down(): void
    {
        //
    }
};
