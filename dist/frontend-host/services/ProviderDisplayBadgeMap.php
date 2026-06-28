<?php

/**
 * games_provider API maskelenmiş görünen ad → eski slug anahtarı (providerBadges).
 */
final class ProviderDisplayBadgeMap
{
    public static function slugForDisplay(string $display): ?string
    {
        static $map = [
            'pragmatic play'   => 'pragmatic',
            'pg soft'          => 'pgsoft',
            'spribe'           => 'spribe',
            'hacksaw'          => 'hacksaw',
            'hacksaw gaming'   => 'hacksaw',
            'nolimit city'     => 'nolimitcity-A',
            'nolimitcity'      => 'nolimitcity-A',
            'nolimitcity a'    => 'nolimitcity-A',
            'bgaming'          => 'bgaming',
            'evoplay'          => 'evoplay',
            'play son'         => 'play-son',
            'playson'          => 'play-son',
            'booming'          => 'booming',
            'booming games'    => 'booming',
            'quickspin'        => 'quickspin',
            'amusnet'          => 'amusnet',
            'egt digital'      => 'egt-digital',
            'egtdigital'       => 'egtdigital',
            'voltent'          => 'voltent',
            'popok'            => 'popok',
            'popok gaming'     => 'popok-gaming',
            'habanero'         => 'habanero',
        ];

        $n = mb_strtolower(trim($display), 'UTF-8');

        return $map[$n] ?? null;
    }
}
