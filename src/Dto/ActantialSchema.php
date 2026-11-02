<?php

namespace App\Dto;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    operations: [new GetCollection()],
    paginationEnabled: false,
    routePrefix: '/metadata',
    provider: 'App\Provider\ActantialSchemaProvider'
)]
class ActantialSchema
{
    // Sans groupe, /api/metadata (sérialisé par groupes) renvoie des objets vides
    #[Groups(["metadata_read"])]
    public string $role;
    #[Groups(["metadata_read"])]
    public string $description;
    #[Groups(["metadata_read"])]
    public string $tendency;
    #[Groups(["metadata_read"])]
    public array $keywords;

    public function __construct(string $role, string $description, string $tendency, array $keywords)
    {
        $this->role = $role;
        $this->description = $description;
        $this->tendency = $tendency;
        $this->keywords = $keywords;
    }
}