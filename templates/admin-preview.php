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
	data-invalid-text="<?php p($l->t('Enter valid appearance settings to update the preview.')); ?>">
	<h2><?php p($l->t('Watermark preview')); ?></h2>
	<p class="settings-hint">
		<?php p($l->t('This sample PDF uses the appearance settings above and refreshes as you edit them.')); ?>
	</p>
	<div class="files-watermark-admin-preview__document" aria-busy="true">
		<iframe
			class="files-watermark-admin-preview__frame"
			title="<?php p($l->t('Watermarked PDF preview')); ?>"></iframe>
	</div>
	<div class="files-watermark-admin-preview__footer">
		<span class="files-watermark-admin-preview__status" role="status" aria-live="polite">
			<?php p($l->t('Rendering watermark preview…')); ?>
		</span>
		<a
			class="files-watermark-admin-preview__open"
			target="_blank"
			rel="noopener noreferrer">
			<?php p($l->t('Open PDF preview')); ?>
		</a>
	</div>
</div>
