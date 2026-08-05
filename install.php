<?php

use Pushword\Installer\PostInstall;
use Pushword\Version\PushwordVersionBundle;

/**
 * Execute via Pushword\Installer\PostInstall::postUpdateCommand.
 */
if (! PostInstall::isRoot()) {
    throw new Exception('installer mus be run from root');
}

PostInstall::registerBundle(PushwordVersionBundle::class);
PostInstall::importRoutes('version', '@PushwordVersionBundle/VersionRoutes.yaml');
