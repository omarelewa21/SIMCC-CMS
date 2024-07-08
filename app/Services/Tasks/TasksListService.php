<?php

namespace App\Services\Tasks;

use App\Abstracts\GetList;
use App\Models\Tasks;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Pagination\LengthAwarePaginator;

class TasksListService extends GetList
{
    protected function getModel(): string
    {
        return Tasks::class;
    }

    protected function getFilterOptionsQuery(): Builder
    {
        return match ($this->request->get('get_filter')) {
            'languages' => $this->getLanguages(),
            'domains'   => $this->getDomains(),
            'tags'      => $this->getTags(),
            'status'    => $this->getStatuses(),
            default     => Tasks::whereRaw('1 = 0'),
        };
    }

    private function getLanguages(): Builder
    {
        return (clone $this->baseQueryForFilters)
            ->join('task_contents', 'tasks.id', '=', 'task_contents.task_id')
            ->join('all_languages', 'all_languages.id', '=', 'task_contents.language_id')
            ->select('all_languages.id as filter_id', 'all_languages.name as filter_name');
    }

    private function getDomains(): Builder
    {
        return (clone $this->baseQueryForFilters)
            ->join('taggables', function (JoinClause $join){
                $join->on('taggables.taggable_id', '=', 'tasks.id')
                    ->where('taggables.taggable_type', Tasks::class);
                })
            ->join('domains_tags', function (JoinClause $join){
                $join->on('domains_tags.id', '=', 'taggables.domains_tags_id')
                    ->where('domains_tags.is_tag', 0);
            })
            ->select('domains_tags.id as filter_id', 'domains_tags.name as filter_name');
    }

    private function getTags(): Builder
    {
        return (clone $this->baseQueryForFilters)
            ->join('taggables', function (JoinClause $join){
                $join->on('taggables.taggable_id', '=', 'tasks.id')
                    ->where('taggables.taggable_type', Tasks::class);
                })
            ->join('domains_tags', function (JoinClause $join){
                $join->on('domains_tags.id', '=', 'taggables.domains_tags_id')
                    ->where('domains_tags.is_tag', 1);
            })
            ->select('domains_tags.id as filter_id', 'domains_tags.name as filter_name');
    }

    private function getStatuses(): Builder
    {
        return (clone $this->baseQueryForFilters)->select("status as filter_id","status as filter_name");
    }

    protected function returnTableData(): LengthAwarePaginator
    {
        return $this->getRespectiveUserTasks()
            ->with($this->getWithRelations())
            ->applyFilters($this->request)
            ->search($this->request->search ?? '')
            ->orderBy('tasks.updated_at', 'desc')
            ->paginate($this->request->limits ?? defaultLimit());
    }

    private function getRespectiveUserTasks(): Builder
    {
        return Tasks::when(
            auth()->user()->isAdminOrSuperAdmin(),
            fn(Builder $query) => $query,
            fn(Builder $query) => $query->where('created_by_userid', auth()->id())
        );
    }

    private function getWithRelations(): array
    {
        $baseRelations = [
            'tags:id,is_tag,domain_id,name',
            'Moderation:moderation_id,moderation_date,moderation_by_userid',
            'Moderation.user:id,username',
            'gradeDifficulty:gradeDifficulty_id,grade,difficulty',
        ];

        return auth()->user()->isAdminOrSuperAdmin()
            ? $baseRelations
            : array_merge($baseRelations, ['taskAnswers:id,task_id,answer,position', 'taskAnswers.taskLabels:id,task_answers_id,lang_id,content']);
    }
}
