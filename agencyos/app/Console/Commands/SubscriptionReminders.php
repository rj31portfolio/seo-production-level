<?php

namespace App\Console\Commands;

use App\Models\Agency;
use App\Models\ClientSubscription;
use App\Models\InAppNotification;
use App\Models\RenewalReminder;
use App\Models\SystemSetting;
use App\Notifications\ClientExpiryReminder;
use App\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class SubscriptionReminders extends Command
{
    protected $signature = 'agencyos:subscription-reminders';

    protected $description = 'Issue idempotent client SEO expiry reminders';

    public function handle(): int
    {
        $thresholds = SystemSetting::expiryRules()['reminders'];
        Agency::where('status', 'active')->eachById(function ($agency) use ($thresholds) {
            app(TenantContext::class)->run($agency, function () use ($agency, $thresholds) {
                ClientSubscription::where('status', 'active')->where('starts_at', '<=', now())->with('client')->eachById(function ($s) use ($agency, $thresholds) {
                    $days = (int) now($agency->timezone)->startOfDay()->diffInDays($s->expires_at->copy()->timezone($agency->timezone)->startOfDay(), false);
                    if (! in_array($days, $thresholds, true) || ! $s->client) {
                        return;
                    }
                    DB::transaction(function () use ($s, $agency, $days) {
                        $locked = ClientSubscription::lockForUpdate()->findOrFail($s->id);
                        $lockedDays = (int) now($agency->timezone)->startOfDay()->diffInDays($locked->expires_at->copy()->timezone($agency->timezone)->startOfDay(), false);
                        if ($locked->status !== 'active' || $lockedDays !== $days) {
                            return;
                        }
                        if (RenewalReminder::where('client_subscription_id', $s->id)->where('expiry', $locked->expires_at)->where('threshold', $days)->exists()) {
                            return;
                        }
                        RenewalReminder::create(['client_subscription_id' => $s->id, 'expiry' => $locked->expires_at, 'threshold' => $days]);
                        foreach ($agency->users as $user) {
                            if ($user->hasPermission('subscriptions.view')) {
                                InAppNotification::create(['user_id' => $user->id, 'title' => 'Client renewal reminder', 'message' => $s->client->name.': '.$days.' days until expiry.', 'url' => '/client-subscriptions/'.$s->id]);
                                $user->notify(new ClientExpiryReminder($s->client->name, $locked->expires_at->timezone($agency->timezone)->format('d M Y'), $days));
                            }
                        }
                        if ($s->client->email) {
                            Notification::route('mail', $s->client->email)->notify(new ClientExpiryReminder($s->client->name, $locked->expires_at->timezone($agency->timezone)->format('d M Y'), $days));
                        }
                    });
                });
            });
        });
        $this->info('Client SEO reminder scan completed.');

        return self::SUCCESS;
    }
}
