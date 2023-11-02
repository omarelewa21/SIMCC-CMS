<?php

namespace App\Services;

use App\Models\DomainsTags;

class DomainTagsService
{
    public static function createTag(array $row)
    {
        $row['domain_id'] = null;
        return self::createHelperForTopicsAndTags($row);
    }

    public static function createTopics(array $row)
    {
        $row['is_tag'] = 0;
        return self::createHelperForTopicsAndTags($row);
    }

    public static function createDomain(array $row)
    {                  
        $domain = DomainsTags::withTrashed()
            ->updateOrCreate([
                'name'      => $row['name'][0],         //first element of name array is reserve for domain
        ], ['name' => $row['name'][0]]);

        unset($row['name'][0]);                         //remove the first element of name array
        $row['domain_id'] = $domain->id;
        $row['is_tag'] = 0;
        return self::createHelperForTopicsAndTags($row);
    }

    private static function createHelperForTopicsAndTags(array $row): void
    {
        foreach($row['name'] as $name) {
            DomainsTags::withTrashed()->updateOrCreate([
                'name'      => $name,
                'is_tag'    => $row['is_tag'],
                'domain_id' => $row['domain_id'],
            ], [
                'name'      => $name,
                'is_tag'    => $row['is_tag'],
                'domain_id' => $row['domain_id'],
            ]);
        }
    }
}
