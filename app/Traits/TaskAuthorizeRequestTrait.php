<?php
namespace App\Traits;

use App\Models\Collections;
use App\Models\CollectionSections;
use App\Models\Tasks;
use Illuminate\Auth\Access\AuthorizationException;

trait TaskAuthorizeRequestTrait
{
    protected $failedTaskName;

    public function authorize()
    {
        return $this->authorizeTaskId($this->id);
    }

    protected function authorizeTaskId($taskId = null)
    {
        if(!$taskId) return true;

        $task = Tasks::find($taskId);
        if(!$task) return true;

        if($this->checkIfTaskIsIncludedInVerifiedCollection($task)){
            $this->failedTaskName = $task->identifier;
            return false;
        }

        return true;
    }

    public function failedAuthorization()
    {
        throw new AuthorizationException("Task '$this->failedTaskName' is included in a verified collection, you cannot modify/delete it.");
    }

    public function checkIfTaskIsIncludedInVerifiedCollection(Tasks $task)
    {
        return CollectionSections::join('collection', 'collection.id', '=', 'collection_sections.collection_id')
            ->where('collection.status', Collections::STATUS_VERIFIED)
            ->where('collection_sections.tasks', 'LIKE', "%$task->id%")
            ->exists();
    }
}
