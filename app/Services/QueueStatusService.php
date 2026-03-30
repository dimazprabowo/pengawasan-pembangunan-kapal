<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class QueueStatusService
{
    /**
     * Check if queue worker is active
     */
    public function isQueueWorkerActive(): bool
    {
        try {
            // Check if there are any jobs being processed in the last 30 seconds
            $recentJobs = DB::table('jobs')
                ->where('reserved_at', '>=', now()->subSeconds(30)->timestamp)
                ->exists();

            if ($recentJobs) {
                Cache::put('queue_worker_active', true, now()->addMinutes(1));
                return true;
            }

            // Check cache for recent activity
            $cachedStatus = Cache::get('queue_worker_active', false);
            
            // If no recent activity, check if there are pending jobs
            $pendingJobs = DB::table('jobs')->exists();
            
            // If there are pending jobs but no recent processing, worker is likely inactive
            if ($pendingJobs && !$cachedStatus) {
                return false;
            }

            return $cachedStatus;
        } catch (\Exception $e) {
            // If jobs table doesn't exist or error, assume inactive
            return false;
        }
    }

    /**
     * Get queue worker status message
     */
    public function getQueueStatusMessage(): array
    {
        $isActive = $this->isQueueWorkerActive();

        if ($isActive) {
            return [
                'active' => true,
                'message' => 'Queue worker aktif - File sedang diproses',
                'icon' => 'processing',
                'color' => 'blue',
            ];
        }

        return [
            'active' => false,
            'message' => 'Queue worker tidak aktif - File menunggu diproses',
            'icon' => 'warning',
            'color' => 'amber',
        ];
    }

    /**
     * Get pending jobs count
     */
    public function getPendingJobsCount(): int
    {
        try {
            return DB::table('jobs')->count();
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Mark queue worker as active (called by jobs)
     */
    public function markWorkerActive(): void
    {
        Cache::put('queue_worker_active', true, now()->addMinutes(1));
    }
}
