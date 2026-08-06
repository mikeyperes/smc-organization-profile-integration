<?php

declare( strict_types=1 );

namespace SMC\OrganizationProfile\Presentation;

use SMC\OrganizationProfile\Data\Normalizer;
use SMC\OrganizationProfile\Data\OrganizationData;

defined( 'ABSPATH' ) || exit;

final class OrganizationProfileRenderer {
    /** @param array<string,mixed> $attributes */
    public static function render( OrganizationData $data, array $attributes = [] ): string {
        $attributes = wp_parse_args(
            $attributes,
            [
                'show_title' => 'true',
                'show_logo'  => 'true',
                'heading'    => 'h1',
            ]
        );

        $heading = in_array( strtolower( (string) $attributes['heading'] ), [ 'h1', 'h2' ], true )
            ? strtolower( (string) $attributes['heading'] )
            : 'h1';
        $show_title = filter_var( $attributes['show_title'], FILTER_VALIDATE_BOOLEAN );
        $show_logo  = filter_var( $attributes['show_logo'], FILTER_VALIDATE_BOOLEAN );

        $name         = $data->name();
        $subtitle     = $data->text( 'slogan' );
        $summary      = $data->text( 'short_description' );
        $company_info = $data->raw( 'company_description' );
        $mission      = $data->raw( 'mission_statement' );
        $focus        = implode( ', ', Normalizer::strings( $data->rows( 'seek_entries' ), [ 'name', 'value' ] ) );
        $legal_name   = $data->text( 'legal_name' );
        $founding     = $data->date( 'founding_date' );
        $website      = $data->url( 'url' );
        $email        = Normalizer::email( $data->raw( 'email' ) );
        $locations    = $data->locations();
        $headquarters = $locations[0] ?? [];
        $logo         = $data->logo();

        $logo_html = '';
        if ( $show_logo && ! empty( $logo['id'] ) ) {
            $logo_html = wp_get_attachment_image(
                absint( $logo['id'] ),
                'medium',
                false,
                [ 'class' => 'smc-organization-profile__logo sfpf-organization-profile__logo', 'alt' => $logo['alt'] ?? $name ]
            );
        } elseif ( $show_logo && ! empty( $logo['url'] ) ) {
            $logo_html = '<img class="smc-organization-profile__logo sfpf-organization-profile__logo" src="'
                . esc_url( (string) $logo['url'] ) . '" alt="' . esc_attr( (string) ( $logo['alt'] ?? $name ) )
                . '" loading="lazy" decoding="async">';
        }

        $details = [];
        if ( '' !== $legal_name ) {
            $details[] = [ 'label' => 'Legal name', 'value' => $legal_name ];
        }
        if ( '' !== $founding ) {
            $details[] = [ 'label' => 'Founded', 'value' => $founding ];
        }
        $location = Normalizer::text( $headquarters['name'] ?? '' );
        if ( '' === $location ) {
            $location = Normalizer::text( $headquarters['address_locality'] ?? '' );
        }
        if ( '' !== $location ) {
            $details[] = [ 'label' => 'Headquarters', 'value' => $location ];
        }

        $links = [];
        if ( '' !== $website ) {
            $links[ $website ] = [ 'label' => 'Website', 'url' => $website, 'primary' => true ];
        }
        foreach ( $data->identity_links() as $identity ) {
            $url = Normalizer::url( $identity['url'] ?? '' );
            if ( '' === $url || isset( $links[ $url ] ) ) {
                continue;
            }
            $label = Normalizer::text( $identity['label'] ?? '' );
            if ( '' === $label ) {
                $label = ucwords( str_replace( [ '-', '_' ], ' ', Normalizer::text( $identity['platform'] ?? 'Link' ) ) );
            }
            $links[ $url ] = [ 'label' => $label, 'url' => $url, 'primary' => false ];
        }

        FrontendAssets::enqueue();
        $title_id = 'smc-organization-title-' . $data->id();
        $html = '<article class="smc-organization-profile sfpf-organization-profile" data-organization-id="' . esc_attr( (string) $data->id() ) . '"';
        if ( $show_title ) {
            $html .= ' aria-labelledby="' . esc_attr( $title_id ) . '"';
        }
        $html .= '><header class="smc-organization-profile__hero sfpf-organization-profile__hero">';
        if ( '' !== $logo_html ) {
            $html .= '<div class="smc-organization-profile__logo-wrap sfpf-organization-profile__logo-wrap">' . $logo_html . '</div>';
        }
        $html .= '<div class="smc-organization-profile__hero-copy sfpf-organization-profile__hero-copy">';
        $html .= '<span class="smc-organization-profile__eyebrow sfpf-organization-profile__eyebrow">Organization</span>';
        if ( $show_title ) {
            $html .= '<' . $heading . ' id="' . esc_attr( $title_id ) . '" class="smc-organization-profile__title sfpf-organization-profile__title">'
                . esc_html( $name ) . '</' . $heading . '>';
        }
        if ( '' !== $subtitle ) {
            $html .= '<p class="smc-organization-profile__subtitle sfpf-organization-profile__subtitle">' . esc_html( $subtitle ) . '</p>';
        }
        if ( '' !== $summary ) {
            $html .= '<div class="smc-organization-profile__summary sfpf-organization-profile__summary">' . esc_html( $summary ) . '</div>';
        }
        if ( [] !== $links ) {
            $html .= '<nav class="smc-organization-profile__links sfpf-organization-profile__links" aria-label="' . esc_attr( $name . ' links' ) . '">';
            foreach ( $links as $link ) {
                $modifier = $link['primary'] ? ' smc-organization-profile__link--primary sfpf-organization-profile__link--primary' : '';
                $html .= '<a class="smc-organization-profile__link sfpf-organization-profile__link' . esc_attr( $modifier )
                    . '" href="' . esc_url( $link['url'] ) . '" target="_blank" rel="noopener noreferrer">'
                    . esc_html( $link['label'] ) . '<span aria-hidden="true"> &rarr;</span></a>';
            }
            $html .= '</nav>';
        }
        $html .= '</div></header>';

        $has_main = Normalizer::present( $company_info ) || Normalizer::present( $mission ) || '' !== $focus;
        if ( $has_main || [] !== $details || '' !== $email ) {
            $html .= '<div class="smc-organization-profile__body sfpf-organization-profile__body">';
            if ( $has_main ) {
                $html .= '<div class="smc-organization-profile__main sfpf-organization-profile__main">';
                foreach ( [ 'About ' . $name => $company_info, 'Mission' => $mission, 'Focus' => $focus ] as $label => $content ) {
                    if ( ! Normalizer::present( $content ) ) {
                        continue;
                    }
                    $html .= '<section class="smc-organization-profile__section sfpf-organization-profile__section"><h2>' . esc_html( $label )
                        . '</h2><div class="smc-organization-profile__rich-text sfpf-organization-profile__rich-text">' . wp_kses_post( (string) $content ) . '</div></section>';
                }
                $html .= '</div>';
            }
            if ( [] !== $details || '' !== $email ) {
                $html .= '<aside class="smc-organization-profile__facts sfpf-organization-profile__facts" aria-label="Organization details"><h2>Company details</h2><dl>';
                foreach ( $details as $detail ) {
                    $html .= '<div class="smc-organization-profile__fact sfpf-organization-profile__fact"><dt>' . esc_html( $detail['label'] )
                        . '</dt><dd>' . esc_html( $detail['value'] ) . '</dd></div>';
                }
                if ( '' !== $email ) {
                    $html .= '<div class="smc-organization-profile__fact sfpf-organization-profile__fact"><dt>Contact</dt><dd><a href="mailto:'
                        . esc_attr( $email ) . '">' . esc_html( $email ) . '</a></dd></div>';
                }
                $html .= '</dl></aside>';
            }
            $html .= '</div>';
        }

        return $html . '</article>';
    }
}
