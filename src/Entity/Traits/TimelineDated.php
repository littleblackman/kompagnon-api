<?php

namespace App\Entity\Traits;

use Doctrine\ORM\Mapping as ORM;

/**
 * Date d'un élément de la chronologie, telle que le texte permet de la fixer.
 *
 * Une date de roman est rarement complète : « l'hiver dernier », « il y a
 * vingt ans », « le lundi ». On garde donc les trois morceaux séparément
 * (une année seule est une vraie date), la précision réelle et l'ancrage,
 * c'est-à-dire le raisonnement qui a permis de la poser.
 */
trait TimelineDated
{
    #[ORM\Column(nullable: true)]
    private ?int $year = null;

    #[ORM\Column(nullable: true)]
    private ?int $month = null;

    #[ORM\Column(nullable: true)]
    private ?int $day = null;

    /** « ~ » : la date est un calage, pas une donnée du texte */
    #[ORM\Column]
    private bool $approximate = false;

    #[ORM\Column(length: 20)]
    private string $datePrecision = 'aucune';

    /** Heure telle que le texte la donne (« 21:03 », « vers 19 h ») */
    #[ORM\Column(length: 30, nullable: true)]
    private ?string $time = null;

    /** Jour de la semaine que le texte affirme, pour le confronter au calendrier */
    #[ORM\Column(length: 10, nullable: true)]
    private ?string $weekday = null;

    /** Comment la date est établie (« dit en déc. 2025, moins 50 ans ») */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $anchor = null;

    public function getYear(): ?int { return $this->year; }
    public function getMonth(): ?int { return $this->month; }
    public function getDay(): ?int { return $this->day; }
    public function isApproximate(): bool { return $this->approximate; }
    public function getDatePrecision(): string { return $this->datePrecision; }
    public function getTime(): ?string { return $this->time; }
    public function getWeekday(): ?string { return $this->weekday; }
    public function getAnchor(): ?string { return $this->anchor; }

    /**
     * Les bornes sont tenues ici : un mois 13 n'est pas une date approximative,
     * c'est une donnée fausse. Un jour sans mois n'a pas de sens non plus.
     */
    public function setDate(?int $year, ?int $month, ?int $day): static
    {
        $this->year = $year !== null && $year >= -5000 && $year <= 5000 ? $year : null;
        $this->month = $this->year !== null && $month !== null && $month >= 1 && $month <= 12 ? $month : null;
        $this->day = $this->month !== null && $day !== null && $day >= 1 && $day <= 31 ? $day : null;
        return $this;
    }

    public function setApproximate(bool $approximate): static
    {
        $this->approximate = $approximate;
        return $this;
    }

    public function setDatePrecision(string $precision): static
    {
        $this->datePrecision = in_array($precision, \App\Service\TimelineCalendar::PRECISIONS, true) ? $precision : 'aucune';
        return $this;
    }

    public function setTime(?string $time): static
    {
        $this->time = $time !== null && trim($time) !== '' ? mb_substr(trim($time), 0, 30) : null;
        return $this;
    }

    public function setWeekday(?string $weekday): static
    {
        $weekday = $weekday !== null ? mb_strtolower(trim($weekday)) : null;
        $this->weekday = in_array($weekday, \App\Service\TimelineCalendar::WEEKDAYS, true) ? $weekday : null;
        return $this;
    }

    public function setAnchor(?string $anchor): static
    {
        $this->anchor = $anchor !== null && trim($anchor) !== '' ? trim($anchor) : null;
        return $this;
    }

    /** Reprend une date venue du modèle ou du formulaire, mêmes clés dans les deux cas */
    public function hydrateDate(array $data): static
    {
        if (array_key_exists('year', $data) || array_key_exists('month', $data) || array_key_exists('day', $data)) {
            $int = fn ($v) => is_numeric($v) ? (int) $v : null;
            $this->setDate($int($data['year'] ?? null), $int($data['month'] ?? null), $int($data['day'] ?? null));
        }
        if (array_key_exists('approximate', $data)) {
            $this->setApproximate((bool) $data['approximate']);
        }
        if (array_key_exists('precision', $data)) {
            $this->setDatePrecision((string) $data['precision']);
        }
        if (array_key_exists('time', $data)) {
            $this->setTime(is_string($data['time']) ? $data['time'] : null);
        }
        if (array_key_exists('weekday', $data)) {
            $this->setWeekday(is_string($data['weekday']) ? $data['weekday'] : null);
        }
        if (array_key_exists('anchor', $data)) {
            $this->setAnchor(is_string($data['anchor']) ? $data['anchor'] : null);
        }
        return $this;
    }

    public function dateToArray(): array
    {
        return [
            'year' => $this->year,
            'month' => $this->month,
            'day' => $this->day,
            'approximate' => $this->approximate,
            'precision' => $this->datePrecision,
            'time' => $this->time,
            'weekday' => $this->weekday,
            'anchor' => $this->anchor,
        ];
    }
}
