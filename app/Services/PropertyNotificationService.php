<?php

namespace App\Services;

use App\Mail\PropertyEventNotificationMail;
use App\Models\Property;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class PropertyNotificationService
{
    /**
     * Send instant notification to Team (Admins, Team Leads, Agents) and Designers based on role & event.
     *
     * Rules:
     * 1. Core team (All Admins, Team Leads, Agents) ALWAYS receive notifications for all property events.
     * 2. On property creation ('created'):
     *    - If actor is Admin: Designers are notified to request promotion/design.
     *    - If actor is Team Lead or Agent: Designers are NOT notified.
     * 3. On price change ('price_changed'):
     *    - If actor is Admin OR property is promoted: Designers are notified.
     * 4. On status change / card moves ('status_changed', 'under_negotiation', 'reserved', 'sold', 'closed'):
     *    - If property is promoted OR actor is Admin: Designers are notified.
     * 5. Recipients are cleaned, dummy seeder emails (@example.com) are filtered out,
     *    and emails are sent individually to guarantee inbox delivery and avoid spam blocks.
     *
     * @param Property $property
     * @param string $eventType 'created', 'price_changed', 'status_changed', 'under_negotiation', 'reserved', 'sold', 'deleted'
     * @param string $eventTitle
     * @param float|null $oldPrice
     * @param float|null $newPrice
     * @param string|null $performedByName
     * @param User|null $actor
     */
    public static function notifyTeam(
        Property $property,
        string $eventType,
        string $eventTitle,
        ?float $oldPrice = null,
        ?float $newPrice = null,
        ?string $performedByName = null,
        ?User $actor = null,
        ?array $targetDesignerIds = null
    ): void {
        try {
            $currentUser = $actor ?: auth()->user();
            $actorName = $performedByName ?: ($currentUser ? $currentUser->name : 'أحد أعضاء الفريق');
            $isActorAdmin = $currentUser ? $currentUser->isAdmin() : false;

            // 1. Core team recipients: All Admins, Team Leads, and Agents
            $teamEmails = User::whereIn('role', ['admin', 'team_lead', 'agent'])
                ->whereNotNull('email')
                ->pluck('email')
                ->map(fn($e) => trim($e))
                ->filter()
                ->unique()
                ->toArray();

            $recipients = $teamEmails;

            // 2. Designer notification: strictly target only selected/assigned designers
            if ($targetDesignerIds !== null) {
                // Explicitly selected designers from creation or confirmation
                if (!empty($targetDesignerIds)) {
                    $designerEmails = User::where('role', 'designer')
                        ->whereIn('id', $targetDesignerIds)
                        ->whereNotNull('email')
                        ->pluck('email')
                        ->map(fn($e) => trim($e))
                        ->filter()
                        ->unique()
                        ->toArray();

                    $recipients = array_merge($recipients, $designerEmails);
                }
                // If $targetDesignerIds is empty ([]), no designers are notified
            } else {
                // If not explicitly passed, check if the property has specifically assigned designers
                $assignedDesignerIds = !empty($property->designer_ids)
                    ? (array) $property->designer_ids
                    : ($property->promoted_designer_id ? [$property->promoted_designer_id] : []);

                if (!empty($assignedDesignerIds)) {
                    $designerEmails = User::where('role', 'designer')
                        ->whereIn('id', $assignedDesignerIds)
                        ->whereNotNull('email')
                        ->pluck('email')
                        ->map(fn($e) => trim($e))
                        ->filter()
                        ->unique()
                        ->toArray();

                    $recipients = array_merge($recipients, $designerEmails);
                }
            }

            // 3. Local Test Override Email (guarantees developer/tester always receives the email)
            if ($testOverrideEmail = env('MAIL_TEST_OVERRIDE_EMAIL')) {
                $recipients[] = trim($testOverrideEmail);
            }

            // 4. Sanitize and remove dummy seeder emails (@example.com / @example.org / @example.net)
            $recipients = array_filter(array_unique(array_map('trim', $recipients)), function ($email) {
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    return false;
                }
                $lower = strtolower($email);
                return !str_contains($lower, '@example.com')
                    && !str_contains($lower, '@example.net')
                    && !str_contains($lower, '@example.org');
            });

            $recipients = array_values($recipients);

            if (empty($recipients)) {
                Log::info("PropertyNotificationService: No valid recipients found for event [{$eventType}] on property #{$property->id}");
                return;
            }

            // Load property relationships if not loaded
            $property->loadMissing(['owner', 'creator']);

            // 5. Send individually to guarantee direct inbox delivery without batch header bounce
            $sentCount = 0;
            foreach ($recipients as $recipientEmail) {
                try {
                    Mail::mailer('notice')
                        ->to($recipientEmail)
                        ->send(new PropertyEventNotificationMail(
                            $property,
                            $eventType,
                            $eventTitle,
                            $oldPrice,
                            $newPrice,
                            $actorName
                        ));
                    $sentCount++;
                } catch (\Throwable $sendErr) {
                    Log::warning("PropertyNotificationService: Failed sending to {$recipientEmail}: " . $sendErr->getMessage());
                }
            }

            Log::info("PropertyNotificationService: Sent [{$eventType}] notice for property #{$property->id} by {$actorName} to ({$sentCount}/" . count($recipients) . ") recipients: " . implode(', ', $recipients));
        } catch (\Throwable $e) {
            Log::error("PropertyNotificationService Failed: " . $e->getMessage(), [
                'property_id' => $property->id,
                'event_type' => $eventType,
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Send notification to Designers when an Admin manually requests promotion for a property
     */
    public static function notifyDesignerPromotionRequest(Property $property, User $admin, ?array $targetDesignerIds = null): void
    {
        try {
            $designerQuery = User::where('role', 'designer')->whereNotNull('email');

            if ($targetDesignerIds !== null) {
                if (empty($targetDesignerIds)) {
                    Log::info("PropertyNotificationService: No designers selected for promotion request on property #{$property->id}");
                    return;
                }
                $designerQuery->whereIn('id', $targetDesignerIds);
            } elseif (!empty($property->designer_ids)) {
                $designerQuery->whereIn('id', (array) $property->designer_ids);
            } elseif ($property->promoted_designer_id) {
                $designerQuery->where('id', $property->promoted_designer_id);
            }

            $recipients = $designerQuery
                ->pluck('email')
                ->map(fn($e) => trim($e))
                ->filter()
                ->unique()
                ->toArray();

            if ($testOverrideEmail = env('MAIL_TEST_OVERRIDE_EMAIL')) {
                $recipients[] = trim($testOverrideEmail);
            }

            $recipients = array_filter(array_unique(array_map('trim', $recipients)), function ($email) {
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    return false;
                }
                $lower = strtolower($email);
                return !str_contains($lower, '@example.com')
                    && !str_contains($lower, '@example.net')
                    && !str_contains($lower, '@example.org');
            });

            $recipients = array_values($recipients);

            if (empty($recipients)) {
                Log::info("PropertyNotificationService: No designers found for promotion request on property #{$property->id}");
                return;
            }

            $property->loadMissing(['owner', 'creator']);

            foreach ($recipients as $recipientEmail) {
                try {
                    Mail::mailer('notice')
                        ->to($recipientEmail)
                        ->send(new PropertyEventNotificationMail(
                            $property,
                            'promotion_requested',
                            'طلب ترويج وتصميم عقار',
                            null,
                            (float) $property->price,
                            $admin->name
                        ));
                } catch (\Throwable $e) {
                    Log::warning("PropertyNotificationService: Failed sending promotion request to {$recipientEmail} via notice mailer: " . $e->getMessage() . " - Retrying via default smtp mailer...");
                    try {
                        Mail::to($recipientEmail)
                            ->send(new PropertyEventNotificationMail(
                                $property,
                                'promotion_requested',
                                'طلب ترويج وتصميم عقار',
                                null,
                                (float) $property->price,
                                $admin->name
                            ));
                    } catch (\Throwable $e2) {
                        Log::error("PropertyNotificationService: Failed sending promotion request to {$recipientEmail} via default mailer: " . $e2->getMessage());
                    }
                }
            }

            Log::info("PropertyNotificationService: Sent promotion request notice for property #{$property->id} to: " . implode(', ', $recipients));
        } catch (\Throwable $e) {
            Log::error("PropertyNotificationService::notifyDesignerPromotionRequest Failed: " . $e->getMessage(), [
                'property_id' => $property->id,
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Send notification to Admin when a Designer completes and submits promotion links
     */
    public static function notifyAdminPromotionCompleted(Property $property, User $designer): void
    {
        try {
            $adminEmails = User::where('role', 'admin')
                ->whereNotNull('email')
                ->pluck('email')
                ->map(fn($e) => trim($e))
                ->filter()
                ->unique()
                ->toArray();

            if ($testOverrideEmail = env('MAIL_TEST_OVERRIDE_EMAIL')) {
                $adminEmails[] = trim($testOverrideEmail);
            }

            $adminEmails = array_filter(array_unique(array_map('trim', $adminEmails)), function ($email) {
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    return false;
                }
                $lower = strtolower($email);
                return !str_contains($lower, '@example.com')
                    && !str_contains($lower, '@example.net')
                    && !str_contains($lower, '@example.org');
            });

            $adminEmails = array_values($adminEmails);

            if (empty($adminEmails)) {
                return;
            }

            $property->loadMissing(['owner', 'creator']);

            foreach ($adminEmails as $adminEmail) {
                try {
                    Mail::mailer('notice')
                        ->to($adminEmail)
                        ->send(new PropertyEventNotificationMail(
                            $property,
                            'promotion_completed',
                            'تم إنجاز الترويج والتصميم وإضافة الروابط',
                            null,
                            (float) $property->price,
                            $designer->name
                        ));
                } catch (\Throwable $e) {
                    Log::warning("PropertyNotificationService: Failed sending promotion completion notice to {$adminEmail}: " . $e->getMessage());
                }
            }

            Log::info("PropertyNotificationService: Sent promotion completion notice for property #{$property->id} to: " . implode(', ', $adminEmails));
        } catch (\Throwable $e) {
            Log::error("PropertyNotificationService::notifyAdminPromotionCompleted Failed: " . $e->getMessage(), [
                'property_id' => $property->id,
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Send notification to Designers when an Admin cancels promotion for a property
     */
    public static function notifyDesignerPromotionCancelled(Property $property, User $admin, ?array $targetDesignerIds = null): void
    {
        try {
            $designerQuery = User::where('role', 'designer')->whereNotNull('email');

            if ($targetDesignerIds !== null && !empty($targetDesignerIds)) {
                $designerQuery->whereIn('id', $targetDesignerIds);
            } elseif (!empty($property->designer_ids)) {
                $designerQuery->whereIn('id', (array) $property->designer_ids);
            } elseif ($property->promoted_designer_id) {
                $designerQuery->where('id', $property->promoted_designer_id);
            }

            $recipients = $designerQuery
                ->pluck('email')
                ->map(fn($e) => trim($e))
                ->filter()
                ->unique()
                ->toArray();

            if ($testOverrideEmail = env('MAIL_TEST_OVERRIDE_EMAIL')) {
                $recipients[] = trim($testOverrideEmail);
            }

            $recipients = array_filter(array_unique(array_map('trim', $recipients)), function ($email) {
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    return false;
                }
                $lower = strtolower($email);
                return !str_contains($lower, '@example.com')
                    && !str_contains($lower, '@example.net')
                    && !str_contains($lower, '@example.org');
            });

            $recipients = array_values($recipients);

            if (empty($recipients)) {
                Log::info("PropertyNotificationService: No designers found to notify for promotion cancellation on property #{$property->id}");
                return;
            }

            $property->loadMissing(['owner', 'creator']);

            foreach ($recipients as $recipientEmail) {
                try {
                    Mail::mailer('notice')
                        ->to($recipientEmail)
                        ->send(new PropertyEventNotificationMail(
                            $property,
                            'promotion_cancelled',
                            'تم إلغاء الترويج',
                            null,
                            (float) $property->price,
                            $admin->name
                        ));
                } catch (\Throwable $e) {
                    try {
                        Mail::to($recipientEmail)
                            ->send(new PropertyEventNotificationMail(
                                $property,
                                'promotion_cancelled',
                                'تم إلغاء الترويج',
                                null,
                                (float) $property->price,
                                $admin->name
                            ));
                    } catch (\Throwable $e2) {
                        Log::error("PropertyNotificationService: Failed sending promotion cancellation to {$recipientEmail}: " . $e2->getMessage());
                    }
                }
            }

            Log::info("PropertyNotificationService: Sent promotion cancellation notice for property #{$property->id} to: " . implode(', ', $recipients));
        } catch (\Throwable $e) {
            Log::error("PropertyNotificationService::notifyDesignerPromotionCancelled Failed: " . $e->getMessage(), [
                'property_id' => $property->id,
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
