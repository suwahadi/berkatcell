<?php

namespace App\Providers;

use App\Models\User;
use App\Services\WishlistService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Login;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        $this->configureDefaults();

        View::composer('emails.*', function ($view): void {
            $view->with([
                'accent' => '#d97706',
                'ink' => '#0f172a',
                'muted' => '#64748b',
                'line' => '#e2e8f0',
            ]);
        });

        Event::listen(Login::class, function (Login $event): void {
            if ($event->user instanceof User) {
                app(WishlistService::class)->migrateGuestToUser($event->user);
            }
        });
    }

    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        Carbon::setLocale('id');
        CarbonImmutable::setLocale('id');

        Model::shouldBeStrict(! app()->isProduction());

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
