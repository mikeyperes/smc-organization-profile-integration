<?php

declare( strict_types=1 );

namespace SMC\OrganizationProfile\Shortcodes;

use SMC\OrganizationProfile\Data\Normalizer;
use SMC\OrganizationProfile\Data\OrganizationData;
use SMC\OrganizationProfile\Presentation\FrontendAssets;

defined( 'ABSPATH' ) || exit;

final class GalleryRenderer {
    public static function render( OrganizationData $data, string $field = 'gallery', string $size = 'large', int $columns = 3 ): string {
        $images = $data->gallery( $field, $size );
        if ( [] === $images ) {
            return '';
        }

        FrontendAssets::enqueue();

        $columns = max( 1, min( 6, $columns ) );
        $html = sprintf(
            '<div class="smc-organization-gallery" data-smc-gallery-count="%d" style="--smc-gallery-columns:%d">',
            count( $images ),
            $columns
        );

        foreach ( $images as $image ) {
            $url = Normalizer::url( $image['url'] ?? '' );
            if ( '' === $url ) {
                continue;
            }

            $full_url = Normalizer::url( $image['full_url'] ?? $url ) ?: $url;
            $alt = Normalizer::text( $image['alt'] ?? $image['title'] ?? $data->name() );
            $caption = Normalizer::text( $image['caption'] ?? '' );
            $width = absint( $image['width'] ?? 0 );
            $height = absint( $image['height'] ?? 0 );

            $html .= '<figure class="smc-organization-gallery__item">';
            $html .= '<a class="smc-organization-gallery__link" href="' . esc_url( $full_url ) . '" target="_blank" rel="noopener">';
            $html .= '<img class="smc-organization-gallery__image" src="' . esc_url( $url ) . '" alt="' . esc_attr( $alt ) . '"';
            if ( $width > 0 && $height > 0 ) {
                $html .= ' width="' . $width . '" height="' . $height . '"';
            }
            $html .= ' loading="lazy" decoding="async"></a>';
            if ( '' !== $caption ) {
                $html .= '<figcaption class="smc-organization-gallery__caption">' . esc_html( $caption ) . '</figcaption>';
            }
            $html .= '</figure>';
        }

        return $html . '</div>';
    }

}
