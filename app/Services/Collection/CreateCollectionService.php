<?php

namespace App\Services\Collection;

use App\Models\Collections;
use Illuminate\Support\Arr;

class CreateCollectionService
{
    public function create(array $data)
    {
        foreach($data as $collectionData)
        {
            $collection = Collections::create($collectionData['settings']);
            $this->addTags($collection, $collectionData['settings']);
            $this->addRecommendedDifficulty($collection, $collectionData);
            $this->addCollectionSections($collection, $collectionData['sections']);
        }
    }

    private function addTags(Collections $collection, array $data)
    {
        if (Arr::has($data, 'tags')) {
            $collection->tags()->attach($data['tags']);
        }
    }

    private function addRecommendedDifficulty(Collections $collection, array $data)
    {
        if (Arr::has($data, 'recommendations') && !empty($data['recommendations'])) {
            foreach($data['recommendations'] as $recommendation) {
                $collection->gradeDifficulty()->create(
                    [
                        "grade" => $recommendation['grade'],
                        "difficulty" => $recommendation['difficulty'],
                    ]
                );
            }
        }
    }

    private function addCollectionSections(Collections $collection, array $data)
    {
        foreach($data as $sectionData)
        {
            $collection->sections()->create(array_merge(
                Arr::except($sectionData, ['groups']),
                ['tasks' => $sectionData['groups']]
            ));
        }
    }
}
