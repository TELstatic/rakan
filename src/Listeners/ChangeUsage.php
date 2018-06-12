<?php

namespace TELstatic\Rakan\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use TELstatic\Rakan\Events\BaseEvent;
use TELstatic\Rakan\Models\Storages;

class ChangeUsage
{
    protected $model;
    protected $action;
    protected $storage;

    /**
     * 文件创建或者删除时 自增自减使用量
     */
    public function handle(BaseEvent $event)
    {
        try {
            $this->model = $event->getModel();
            $this->action = $event->getAction();

            $where = [
                'target_id' => $this->model->target_id
            ];

            $this->storage = Storages::where($where)->firstOrFail();

            switch ($this->action) {
                case "created":
                    $this->storage->increment('usage', $this->model->size);
                    break;
                case "deleted":
                    $this->storage->decrement('usage', $this->model->size);
                    break;
            }
        } catch (\Exception $e) {
            Log::error($e->getMessage());
        }
    }
}
