<?php
namespace App\Traits;

use App\Models\Tasks;
use Illuminate\Auth\Access\AuthorizationException;

trait TaskAuthorizeRequestTrait
{
    protected $failedTaskName;

    public function authorize()
    {
        if(auth()->user()->hasRole('Super Admin')) return true;

        return $this->authorizeTaskId($this->id);
    }

    protected function authorizeTaskId($taskId = null)
    {
        if (!$taskId) return true;

        $task = Tasks::find($taskId);
        if (!$task) return true;

        if ($this->isTaskVerified($task)) {
            $this->failedTaskName = $task->identifier;
            return false;
        }

        return true;
    }

    public function failedAuthorization()
    {
        throw new AuthorizationException("Task '$this->failedTaskName' is verified, you cannot modify it.");
    }

    public function isTaskVerified(Tasks $task)
    {
        return $task->status === Tasks::STATUS_VERIFIED;
    }
}
