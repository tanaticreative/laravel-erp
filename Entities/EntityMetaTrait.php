<?php


namespace Tan\ERP\Entities;

use Illuminate\Support\Carbon;

trait EntityMetaTrait
{
    /**
     * Sets meta description
     */
    protected function withMetaDescription()
    {
        if (!$this->model) {
            return;
        }
        $class = get_class($this->model);
        $updatedAt = Carbon::now()->format('Y-m-d H:i:s');
        $this->description = ":::{$class}::{$this->model->id}::{$updatedAt}:::";
    }
}
