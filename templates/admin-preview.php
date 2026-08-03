<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Watermarked shares contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

script('files_watermark', 'files_watermark-admin-preview');
?>

<div
	id="files-watermark-admin-preview"
	class="section files-watermark-admin-preview"
	data-preview-url="<?php p($_['previewUrl']); ?>"
	data-loading-text="<?php p($l->t('Rendering watermark preview…')); ?>"
	data-invalid-text="<?php p($l->t('Enter valid appearance settings to update the preview.')); ?>"
	data-error-text="<?php p($l->t('The watermarked PDF could not be generated.')); ?>">
	<h2><?php p($l->t('Watermark preview')); ?></h2>
	<p class="settings-hint">
		<?php p($l->t('This sample PDF uses the appearance settings above and refreshes as you edit them.')); ?>
	</p>
	<div class="files-watermark-admin-preview__document" aria-busy="false">
		<img
			class="files-watermark-admin-preview__image"
			src="<?php p($_['previewImageUrl']); ?>"
			alt="<?php p($l->t('Watermarked PDF preview')); ?>">
	</div>
	<div class="files-watermark-admin-preview__footer">
		<span class="files-watermark-admin-preview__status" role="status" aria-live="polite"></span>
		<a
			class="files-watermark-admin-preview__open"
			href="<?php p($_['previewUrl']); ?>"
			target="_blank"
			rel="noopener noreferrer">
			<?php p($l->t('Open PDF preview')); ?>
		</a>
	</div>
</div>
