# Staging catalog workers

The price queue needs PHP memory_limit=512M; Laravel --memory=384 recycles it between jobs. The default PHP limit of 128M cannot prepare a 4,164-row supplier price. The queue listens to default,catalog-prices, timeout=600; database retry_after must exceed that (660).

Install deploy/altech-staging-queue.service.example and deploy/altech-staging-photos.service.example as the corresponding systemd units. Both run as altech-staging, scoped to /srv/altech/staging/current. Set CATALOG_PHOTOS_ENABLED=true only in staging shared/.env, rebuild config cache, and create /etc/altech-staging/photos-enabled after deploying code that supports this setting. Local/testing photo processing remains enabled; other deployments default to disabled.

The queue Wants the photo service; the photo service is PartOf the queue. Existing deploy/rollback stop and restart commands therefore stop and restart both workers together, loading the new current release. Do not run a second scheduler photo worker on staging. Photo tasks have expiring leases and recover interrupted work. Automatic worker restarts do not publish products.

Verify queue and photos units are active, an uploaded XLSX reaches ready, and catalog_photo_tasks records leave queued through the real Python processor. Missing or restricted source imagery is reported for review; it must not be replaced by an unverified image. Applying a price and publishing products remain explicit admin actions.
