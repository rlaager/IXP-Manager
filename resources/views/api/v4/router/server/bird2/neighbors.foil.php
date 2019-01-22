<?php
/*
 * Bird Route Server Configuration Template
 *
 *
 * You should not need to edit these files - instead use your own custom skins. If
 * you can't effect the changes you need with skinning, consider posting to the mailing
 * list to see if it can be achieved / incorporated.
 *
 * Skinning: https://ixp-manager.readthedocs.io/en/latest/features/skinning.html
 *
 * Copyright (C) 2009 - 2019 Internet Neutral Exchange Association Company Limited By Guarantee.
 * All Rights Reserved.
 *
 * This file is part of IXP Manager.
 *
 * IXP Manager is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by the Free
 * Software Foundation, version v2.0 of the License.
 *
 * IXP Manager is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
 * FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for
 * more details.
 *
 * You should have received a copy of the GNU General Public License v2.0
 * along with IXP Manager.  If not, see:
 *
 * http://www.gnu.org/licenses/gpl-2.0.html
 */
?>

<?php
    // NOTE: fvliid is used below to distinguish between multiple VLAN interfaces
    //   for the same customer in the same peering LAN

    // only define one filter per ASN
    $asn_filters = [];
?>

########################################################################################
########################################################################################
#
# Route server clients
#
########################################################################################
########################################################################################

<?php foreach( $t->ints as $int ):

        // do not set up a session to ourselves!
        if( $int['autsys'] == $t->router->asn() ):
            continue;
        endif;
?>

########################################################################################
########################################################################################
###
### AS<?= $int['autsys'] ?> - <?= $int['cname'] ?> - VLAN Interface #<?= $int['vliid'] ?>


<?= $t->ipproto ?> table t_<?= $int['fvliid'] ?>_as<?= $int['autsys'] ?>;

<?php
    if( !in_array( $int['autsys'], $asn_filters ) ):

        $asn_filters[] = $int['autsys'];
?>

filter f_import_as<?= $int['autsys'] ?>

prefix set allnet;
int set allas;
{
    if !(avoid_martians()) then {
        bgp_large_community.add( IXP_LC_FILTERED_BOGON );
        accept;
    }

    # Route servers peering with route servers will cause the universe
    # to collapse.  Recommend evasive manoeuvers.
    if (bgp_path.first != <?= $int['autsys'] ?> ) then {
        bgp_large_community.add( IXP_LC_FILTERED_FIRST_AS_NOT_PEER_AS );
        accept;
    }

    # Filter Known Transit Networks
    if filter_has_transit_path() then accept;

    # Belt and braces: no one needs an ASN path with > 64 hops, that's just broken
    if( bgp_path.len > 64 ) then {
        bgp_large_community.add( IXP_LC_FILTERED_AS_PATH_TOO_LONG );
        accept;
    }

<?php if( $t->router->rpki() ): ?>

    # RPKI test - if it's INVALID or VALID, we are done
    if filter_rpki() then accept;

<?php else: ?>

    # Skipping RPKI check, protocol not enabled.
    bgp_large_community.add( IXP_LC_INFO_RPKI_NOT_CHECKED );

<?php endif; ?>



<?php
    // Only do IRRDB ASN/prefix filtering if this is enabled per client:
    if( $int['irrdbfilter'] ?? true ):

        if( count( $int['irrdbfilter_asns'] ) ):
?>

    allas = [ <?php echo $t->softwrap( $int['irrdbfilter_asns'], 10, ", ", ",", 14, 7 ); ?>

    ];

<?php   else: ?>

    allas = [ <?= $int['autsys'] ?> ];

<?php   endif; ?>

    if !(bgp_path.last ~ allas) then {
        bgp_large_community.add( IXP_LC_FILTERED_IRRDB_ORIGIN_AS_FILTERED );
        accept;
    }

<?php
        if( count( $int['irrdbfilter_prefixes'] ) ):
?>

    allnet = [ <?php echo $t->softwrap( $int['rsmorespecifics']
            ? $t->bird()->prefixExactToLessSpecific( $int['irrdbfilter_prefixes'], $t->router->protocol(), config( 'ixp.irrdb.min_v' . $t->router->protocol() . '_subnet_size' ) )
            : $int['irrdbfilter_prefixes'], 4, ", ", ",", 15, $t->router->protocol() == 6 ? 36 : 26 ); ?>

    ];

    if ! (net ~ allnet) then {
        bgp_large_community.add( IXP_LC_FILTERED_IRRDB_PREFIX_FILTERED );
        bgp_large_community.add( <?= $int['rsmorespecifics'] ? 'IXP_LC_FILTERED_IRRDB_FILTERED_LOOSE' : 'IXP_LC_FILTERED_IRRDB_FILTERED_STRICT' ?> );
        accept;
    } else {
        bgp_large_community.add( IXP_LC_INFO_IRRDB_VALID );
    }

<?php   else: ?>

    # Deny everything because the IRR database returned nothing
    bgp_large_community.add( IXP_LC_FILTERED_IRRDB_PREFIX_FILTERED );
    bgp_large_community.add( IXP_LC_FILTERED_IRRDB_PREFIX_EMPTY );
    accept;

<?php   endif; ?>

<?php else: ?>

    # This ASN was configured not to use IRRDB filtering
    bgp_large_community.add( IXP_LC_INFO_IRRDB_NOT_CHECKED );

<?php endif; ?>

    accept;
}


<?php
    endif; // if( !in_array( $asn_filters[ $int['autsys'] ] ) ):
?>

protocol pipe pp_<?= $int['fvliid'] ?>_as<?= $int['autsys'] ?> {
        description "Pipe for AS<?= $int['autsys'] ?> - <?= $int['cname'] ?> - VLAN Interface <?= $int['vliid'] ?>";
        table master<?= $t->router->protocol() ?>;
        peer table t_<?= $int['fvliid'] ?>_as<?= $int['autsys'] ?>;
        import filter f_export_to_master;
        export where ixp_community_filter(<?= $int['autsys'] ?>);
}

protocol bgp pb_<?= $int['fvliid'] ?>_as<?= $int['autsys'] ?> from tb_rsclient {
        description "AS<?= $int['autsys'] ?> - <?= $int['cname'] ?>";
        neighbor <?= $int['address'] ?> as <?= $int['autsys'] ?>;
        <?= $t->ipproto ?> {
            import limit <?= $int['maxprefixes'] ?> action restart;
            import filter f_import_as<?= $int['autsys'] ?>;
            table t_<?= $int['fvliid'] ?>_as<?= $int['autsys'] ?>;
        };
        <?php if( $int['bgpmd5secret'] && !$t->router->skipMD5() ): ?>password "<?= $int['bgpmd5secret'] ?>";<?php endif; ?>

}

<?php endforeach; ?>

