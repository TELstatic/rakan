<?php

namespace TELstatic\Rakan;

use Illuminate\Support\ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        'TELstatic\Rakan\Events\FileCreated' => [
            'TELstatic\Rakan\Listeners\ChangeUsage'
        ],
        'TELstatic\Rakan\Events\FileDeleted' => [
            'TELstatic\Rakan\Listeners\ChangeUsage'
        ]
    ];

    public function boot()
    {
        $events = app('events');
        foreach ($this->listen as $event => $listeners) {
            foreach ($listeners as $listener) {
                $events->listen($event, $listener);
            }
        }
    }

    public function register()
    {
        //
    }

    public function listens()
    {
        return $this->listen;
    }

}