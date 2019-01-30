<div class="btn-group btn-group-sm">

    <a class="btn btn-outline-secondary" href="<?= route($t->feParams->route_prefix.'@view' , [ 'id' => $t->row[ 'id' ] ] ) ?>" title="Preview">
        <i class="fa fa-eye"></i>
    </a>

    <?php if( !isset( $t->feParams->readonly ) || !$t->feParams->readonly ): ?>
        <a class="btn btn-outline-secondary" href="<?= route($t->feParams->route_prefix.'@edit' , [ 'id' => $t->row[ 'id' ] ] ) ?> " title="Edit">
            <i class="fa fa-pencil"></i>
        </a>
        <a class="btn btn-outline-secondary" id='d2f-list-delete-<?= $t->row[ 'id' ] ?>' href="#" data-object-id="<?= $t->row[ 'id' ] ?>" title="Delete">
            <i class="fa fa-trash"></i>
        </a>
    <?php endif;?>

    <a class="btn btn-outline-secondary" href="<?= route('console-server-connection@listPort' , [ 'port' => $t->row[ 'id' ] ] ) ?>" title="List Console Server Connections">
        <i class="fa fa-list"></i>
    </a>
</div>
