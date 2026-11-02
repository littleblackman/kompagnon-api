<?php

namespace App\Service;

/**
 * Arithmétique de calendrier de la chronologie, sans état ni base.
 *
 * Tout ce qui se calcule se calcule ici, pas dans le modèle : un LLM se
 * trompe volontiers sur le jour de la semaine du 6 avril 2026 ou sur l'écart
 * entre deux dates, PHP jamais.
 */
class TimelineCalendar
{
    /** Du plus fin au plus flou */
    public const PRECISIONS = ['heure', 'jour', 'semaine', 'mois', 'saison', 'annee', 'decennie', 'ordre', 'aucune'];

    /** Ordre ISO : lundi = 1 */
    public const WEEKDAYS = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];

    public const PRECISION_LABELS = [
        'heure' => 'jour + heure',
        'jour' => 'jour',
        'semaine' => 'semaine',
        'mois' => 'mois',
        'saison' => 'saison',
        'annee' => 'année',
        'decennie' => 'décennie',
        'ordre' => 'ordre seulement',
        'aucune' => 'sans date',
    ];

    /** Précisions assez fines pour qu'un retour en arrière se remarque */
    private const FINE = ['heure', 'jour', 'semaine', 'mois'];

    /**
     * Clé de tri AAAAMMJJ. Une date partielle se range au milieu de ce
     * qu'elle couvre : une année seule en juin, un mois seul le 15.
     */
    public function sortKey(?int $year, ?int $month, ?int $day): ?int
    {
        if ($year === null) {
            return null;
        }

        return $year * 10000 + ($month ?? 6) * 100 + ($day ?? 15);
    }

    /** Bornes [début, fin] couvertes par une date partielle, en clés AAAAMMJJ */
    public function range(?int $year, ?int $month, ?int $day): ?array
    {
        if ($year === null) {
            return null;
        }
        if ($month === null) {
            return [$year * 10000 + 101, $year * 10000 + 1231];
        }
        if ($day === null) {
            return [$year * 10000 + $month * 100 + 1, $year * 10000 + $month * 100 + 31];
        }

        $key = $year * 10000 + $month * 100 + $day;
        return [$key, $key];
    }

    public function isFine(string $precision): bool
    {
        return in_array($precision, self::FINE, true);
    }

    /** Une date complète qui n'existe pas (31 février) */
    public function isImpossible(?int $year, ?int $month, ?int $day): bool
    {
        return $year !== null && $month !== null && $day !== null && !checkdate($month, $day, $year);
    }

    public function toDate(?int $year, ?int $month, ?int $day): ?\DateTimeImmutable
    {
        if ($year === null || $month === null || $day === null || !checkdate($month, $day, $year)) {
            return null;
        }

        return (new \DateTimeImmutable())->setDate($year, $month, $day)->setTime(0, 0);
    }

    public function weekdayOf(?int $year, ?int $month, ?int $day): ?string
    {
        $date = $this->toDate($year, $month, $day);

        return $date ? self::WEEKDAYS[(int) $date->format('N') - 1] : null;
    }

    /** Jours écoulés depuis J0, négatif avant */
    public function dayOffset(?int $year, ?int $month, ?int $day, ?\DateTimeImmutable $dayZero): ?int
    {
        $date = $this->toDate($year, $month, $day);
        if (!$date || !$dayZero) {
            return null;
        }

        return (int) $dayZero->diff($date)->format('%r%a');
    }

    /** « A0 », « A+1 », « A−3 » */
    public function relativeYear(int $year, ?int $yearZero): string
    {
        if ($yearZero === null) {
            return (string) $year;
        }
        $delta = $year - $yearZero;

        return $delta === 0 ? 'A0' : ($delta > 0 ? 'A+' . $delta : 'A−' . -$delta);
    }

    /**
     * Les deux écritures d'une date : absolue (19/09/2025) et relative à A0
     * (19/09/A0). « ~ » devant une date calée, « ?? » pour le jour inconnu.
     *
     * @return array{absolute: string, relative: string, short: string}
     */
    public function format(?int $year, ?int $month, ?int $day, bool $approximate, ?int $yearZero): array
    {
        if ($year === null) {
            return ['absolute' => '—', 'relative' => '—', 'short' => '—'];
        }

        $prefix = $approximate ? '~' : '';
        $p2 = fn (int $n) => str_pad((string) $n, 2, '0', STR_PAD_LEFT);
        $relYear = $this->relativeYear($year, $yearZero);

        if ($month === null) {
            return ['absolute' => $prefix . $year, 'relative' => $prefix . $relYear, 'short' => $prefix . $year];
        }
        if ($day === null) {
            return [
                'absolute' => $prefix . $p2($month) . '/' . $year,
                'relative' => $prefix . '??/' . $p2($month) . '/' . $relYear,
                'short' => $prefix . $p2($month) . '/' . $year,
            ];
        }

        return [
            'absolute' => $prefix . $p2($day) . '/' . $p2($month) . '/' . $year,
            'relative' => $prefix . $p2($day) . '/' . $p2($month) . '/' . $relYear,
            'short' => $prefix . $p2($day) . '/' . $p2($month),
        ];
    }

    /**
     * Précision déduite des morceaux renseignés, quand le modèle ou le
     * formulaire n'en donne pas de cohérente : un jour sans précision est un
     * jour, une précision « jour » sans jour n'en est pas une.
     */
    public function coherentPrecision(string $precision, ?int $year, ?int $month, ?int $day, ?string $time): string
    {
        $derived = match (true) {
            $day !== null && $time !== null => 'heure',
            $day !== null => 'jour',
            $month !== null => 'mois',
            $year !== null => 'annee',
            default => null,
        };

        if ($derived === null) {
            return in_array($precision, ['ordre', 'aucune'], true) ? $precision : 'aucune';
        }

        // Une précision plus fine que ce qui est renseigné est fausse ;
        // plus floue, elle dit quelque chose (« une semaine », « l'été »).
        $rank = array_flip(self::PRECISIONS);
        if (!isset($rank[$precision]) || in_array($precision, ['ordre', 'aucune'], true) || $rank[$precision] < $rank[$derived]) {
            return $derived;
        }

        return $precision;
    }
}
