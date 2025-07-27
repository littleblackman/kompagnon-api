<?php

namespace App\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Dto\ActantialSchema;

class ActantialSchemaProvider implements ProviderInterface
{
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): iterable
    {
        return [
            new ActantialSchema(
                "Sujet", 
                "Celui qui poursuit un but ou une quête", 
                "positive", 
                ["héros", "acteur principal", "porteur d'action"]
            ),
            new ActantialSchema(
                "Objet", 
                "Ce que le sujet cherche à obtenir, atteindre ou transformer", 
                "ambiguous", 
                ["but", "quête", "enjeu"]
            ),
            new ActantialSchema(
                "Adjuvant", 
                "Ce qui aide le sujet dans sa quête", 
                "positive", 
                ["allié", "facilitateur", "soutien"]
            ),
            new ActantialSchema(
                "Opposant", 
                "Ce qui s'oppose au sujet ou l'empêche d'agir", 
                "negative", 
                ["obstacle", "antagoniste", "entrave"]
            ),
            new ActantialSchema(
                "Destinateur", 
                "Ce qui motive le sujet à agir", 
                "ambiguous", 
                ["cause", "ordre", "valeur donnée", "mandat"]
            ),
            new ActantialSchema(
                "Destinataire", 
                "Celui qui bénéficie de l'action du sujet", 
                "ambiguous", 
                ["bénéficiaire", "héritier", "public"]
            )
        ];
    }
}