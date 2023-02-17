<?php

namespace App\Http\Services;

class TasksService
{
    /**
     * get tasks list query
     */
    public function listQuery()
    {
        $eagerload = [
            'tags:id,is_tag,domain_id,name',
            'Moderation:moderation_id,moderation_date,moderation_by_userid',
            'Moderation.user:id,username',
            'gradeDifficulty:gradeDifficulty_id,grade,difficulty',
        ];
        
        if(auth()->user()->hasRole(['super admin', 'admin'])){
            $eagerload = array_merge($eagerload, [
                'taskAnswers:id,task_id,answer,position',
                'taskAnswers.taskLabels:task_answers_id,lang_id,content'
            ]);
        }
    }
}