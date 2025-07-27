<?php

namespace App\Utils;

class SlugUtils
{
    /**
     * Create a slug from first name and last name
     */
    public static function createPersonnageSlug(?string $firstName, ?string $lastName): string
    {
        $fullName = trim(($firstName ?? '') . ' ' . ($lastName ?? ''));
        
        if (empty($fullName)) {
            return '';
        }
        
        return self::slugify($fullName);
    }
    
    /**
     * Generic slugify function
     */
    public static function slugify(string $text): string
    {
        // Convert to lowercase
        $text = mb_strtolower($text, 'UTF-8');
        
        // Remove accents
        $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        
        // Replace non-alphanumeric characters with hyphens
        $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
        
        // Replace spaces with hyphens
        $text = preg_replace('/\s+/', '-', $text);
        
        // Replace multiple hyphens with single hyphen
        $text = preg_replace('/-+/', '-', $text);
        
        // Remove leading and trailing hyphens
        $text = trim($text, '-');
        
        return $text;
    }
}