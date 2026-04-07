<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class QueueStatusService
{
    /**
     * Check if queue worker is active
     * 
     * Detection strategy:
     * 1. Check cache (set by jobs when they execute)
     * 2. Check for jobs being processed (reserved_at within last 60s)
     * 3. Check for very recent pending jobs (created within last 10s) - gives grace period
     * 4. Check for recent failed jobs (indicates worker is running but jobs failing)
     * 
     * The 10-second grace period for pending jobs prevents false negatives when:
     * - A job is just dispatched but worker hasn't picked it up yet
     * - User clicks generate and immediately sees the status
     * 
     * @return bool True if worker appears to be active, false otherwise
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

            // Check for pending jobs (not yet reserved) created in the last 10 seconds
            // This handles the case when a job is just dispatched but not yet picked up by worker
            // If there are recent pending jobs, assume worker is running and will pick them up soon
            $veryRecentPendingJobs = DB::table('jobs')
                ->whereNull('reserved_at')
                ->where('created_at', '>=', now()->subSeconds(10)->timestamp)
                ->exists();

            if ($veryRecentPendingJobs) {
                // Job just created, give worker a chance to pick it up
                // Don't show warning immediately
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

            // Final check: if there are pending jobs older than 10 seconds,
            // worker might not be running
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
        Cache::put('queue_worker_last_seen', now()->timestamp, now()->addMinutes(10));
    }
}
