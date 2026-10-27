<?php

namespace App\Console\Commands;

use App\Mail\PropertyNeedsUpdateMail;
use App\Models\Property;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CheckStalePropertiesCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'properties:check-stale {--days=60 : Days threshold to consider property stale}';

    /**
     * The console command description.
     */
    protected $description = 'Check properties that have not been updated for 60 days and notify agents, team leads, and admin via email';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $days = (int)$this->option('days') ?: 60;
        $thresholdDate = now()->subDays($days);

        $this->info("Checking for properties not updated since: " . $thresholdDate->toDateTimeString());

        // Find properties active in market with updated_at <= 60 days ago
        $staleProperties = Property::whereIn('status', ['new', 'available', 'under_negotiation'])
            ->where('updated_at', '<=', $thresholdDate)
            ->with(['owner.teamLead', 'creator'])
            ->get();

        $count = $staleProperties->count();
        $this->info("Found {$count} properties needing update.");

        if ($count === 0) {
            return Command::SUCCESS;
        }

        $noticeAdminEmail = config('mail.mailers.notice.username', 'notice@rabah.eg');

        foreach ($staleProperties as $property) {
            $daysSince = (int)now()->diffInDays($property->updated_at);
            $recipients = [];

            // 1. Agent (owner)
            if ($property->owner && !empty($property->owner->email)) {
                $recipients[] = $property->owner->email;
            }

            // 2. Team Lead
            $ccRecipients = [];
            if ($property->owner && $property->owner->teamLead && !empty($property->owner->teamLead->email)) {
                $ccRecipients[] = $property->owner->teamLead->email;
            }

            // 3. Admin / System Notice
            if ($noticeAdminEmail && !in_array($noticeAdminEmail, $recipients)) {
                $ccRecipients[] = $noticeAdminEmail;
            }

            if (empty($recipients)) {
                $recipients[] = $noticeAdminEmail;
            }

            try {
                $mail = new PropertyNeedsUpdateMail($property, $daysSince);

                $pendingMail = Mail::mailer('notice')->to($recipients);
                if (!empty($ccRecipients)) {
                    $pendingMail->cc($ccRecipients);
                }

                $pendingMail->send($mail);

                $this->line("Sent alert for property #{$property->id} to: " . implode(', ', $recipients));
            } catch (\Throwable $e) {
                $this->error("Failed to send alert for property #{$property->id}: " . $e->getMessage());
                Log::error("CheckStalePropertiesCommand Error for property #{$property->id}: " . $e->getMessage());
            }
        }

        $this->info("Completed sending stale properties alerts.");
        return Command::SUCCESS;
    }
}
