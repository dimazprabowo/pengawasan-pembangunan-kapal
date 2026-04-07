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
            // Check cache first (set by jobs when they run)
            $cachedStatus = Cache::get('queue_worker_active', false);
            
            if ($cachedStatus) {
                return true;
            }

            // Check if there are any jobs being processed in the last 60 seconds
            $recentJobs = DB::table('jobs')
                ->where('reserved_at', '>=', now()->subSeconds(60)->timestamp)
                ->exists();

            if ($recentJobs) {
                Cache::put('queue_worker_active', true, now()->addMinutes(5));
                return true;
            }

            // Check failed_jobs table for recent activity (last 2 minutes)
            $recentFailedJobs = DB::table('failed_jobs')
                ->where('failed_at', '>=', now()->subMinutes(2))
                ->exists();
            
            if ($recentFailedJobs) {
                // Worker is active but jobs are failing
                return true;
            }

            return false;
        } catch (\Exception $e) {
            // If tables don't exist or error, assume inactive
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
        Cache::put('queue_worker_active', true, now()->addMinutes(5));
    }
}
