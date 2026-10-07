<?php

namespace Modules\Search\Controllers;

use Modules\Search\Services\SearchService;

final class SearchController
{
    public function __construct(private SearchService $search)
    {
    }

    public function jobs(string $keyword = '', string $location = ''): array
    {
        return $this->search->jobs($keyword, $location);
    }
}
