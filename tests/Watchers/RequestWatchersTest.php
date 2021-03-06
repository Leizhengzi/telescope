<?php

namespace Laravel\Telescope\Tests\Watchers;

use Illuminate\Support\Facades\Route;
use Laravel\Telescope\Storage\EntryModel;
use Laravel\Telescope\Tests\FeatureTestCase;
use Laravel\Telescope\Watchers\RequestWatcher;

class RequestWatchersTest extends FeatureTestCase
{
    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app->get('config')->set('telescope.watchers', [
            RequestWatcher::class => true,
        ]);
    }

    public function test_request_watcher_registers_requests()
    {
        Route::get('/emails', function () {
            return ['email' => 'themsaid@laravel.com'];
        });

        $this->get('/emails')
            ->assertSuccessful()
            ->terminateTelescope();

        $this->assertDatabaseHas('telescope_entries', [
            'type' => 'request'
        ]);

        $entry = EntryModel::query()->first();

        self::assertSame('GET', $entry->content['method']);
        self::assertSame(200, $entry->content['response_status']);
        self::assertSame('emails', $entry->content['uri']);
    }

    public function test_request_watcher_registers_404()
    {
        $this->get('/whatever')->terminateTelescope();

        $this->assertDatabaseHas('telescope_entries', [
            'type' => 'request'
        ]);

        $entry = EntryModel::query()->first();

        self::assertSame('GET', $entry->content['method']);
        self::assertSame(404, $entry->content['response_status']);
        self::assertSame('whatever', $entry->content['uri']);
    }

    public function test_request_watcher_hides_password()
    {
        Route::post('/auth', function () {
            return response('success');
        });

        $this->post('/auth', [
            'email' => 'telescope@laravel.com',
            'password' => 'secret',
            'password_confirmation' => 'secret',
        ])->terminateTelescope();

        $this->assertDatabaseHas('telescope_entries', [
            'type' => 'request'
        ]);

        $entry = EntryModel::query()->first();

        self::assertSame('POST', $entry->content['method']);
        self::assertSame('telescope@laravel.com', $entry->content['payload']['email']);
        self::assertSame('********', $entry->content['payload']['password']);
        self::assertSame('********', $entry->content['payload']['password_confirmation']);
    }
}
