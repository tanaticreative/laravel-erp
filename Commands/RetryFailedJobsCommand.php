<?php

namespace Tan\ERP\Commands;

use Illuminate\Queue\Console\RetryCommand;
use Illuminate\Support\Facades\Config;

class RetryFailedJobsCommand extends RetryCommand
{
    protected $name = 'erp:retry-all';
    protected $signature = 'erp:retry-all';
    protected $description = 'Retry all failed ERP jobs';


    /**
     * {@inheritdoc}
     */
    public function handle()
    {
        /** @var \Illuminate\Queue\Failed\DatabaseFailedJobProvider $failer */
        $failer = $this->laravel['queue.failer'];
        $jobs = $failer->all();

        /** @var \StdClass $failedJob {id,connection,queue,payload,exception,failed_at} */
        foreach ($jobs as $failedJob) {
            if ($failedJob->queue !== Config::get('erp.queue')) {
                continue;
            }

            $this->retryJob($failedJob);
            $this->info("The failed ERP job [{$failedJob->id}] has been pushed back onto the queue!");
            $failer->forget($failedJob->id);
        }
    }
}
