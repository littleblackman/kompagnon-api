<?php

namespace App\Dto;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;

#[ApiResource(
    operations: [new GetCollection()],
    paginationEnabled: false,
    routePrefix: '/metadata',
    provider: 'App\Provider\ActantialSchemaProvider'
)]
class ActantialSchema
{
    public string $role;
    public string $description;
    public string $tendency;
    public array $keywords;

    public function __construct(string $role, string $description, string $tendency, array $keywords)
    {
        $this->role = $role;
        $this->description = $description;
        $this->tendency = $tendency;
        $this->keywords = $keywords;
    }
}