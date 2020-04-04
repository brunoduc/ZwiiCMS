<?php if($module::$galleries): ?>
	<?php $i = 1; ?>
	<?php $galleriesNb = count($module::$galleries); ?>
	<?php foreach($module::$galleries as $galleryId => $gallery): ?>
		<?php if($i % 2 === 1): ?>
			<div class="row">
		<?php endif; ?>		
			<div class="col6" div="pos<?php echo $gallery['config']['position']; ?>" >
				<a
					href="<?php echo helper::baseUrl() . $this->getUrl(0); ?>/<?php echo $galleryId; ?>"
					class="galleryPicture"
					style="background-image:url('<?php 					
					if ($this->getData(['module', $this->getUrl(0), $galleryId, 'config', 'homePicture']) === null ) {
						echo helper::baseUrl(false) . $module::$firstPictures[$galleryId];						
					} else {
						echo helper::baseUrl(false) . $this->getData(['module', $this->getUrl(0), $galleryId, 'config', 'directory']) . '/' . $this->getData(['module', $this->getUrl(0), $galleryId, 'config', 'homePicture']); 
					}					
					?>')"
				>
					<div class="galleryName"><?php echo $gallery['config']['name']; ?></div>
				</a>
			</div>
		<?php if($i % 2 === 0 OR $i === $galleriesNb): ?>
			</div>
		<?php endif; ?>
		<?php $i++; ?>
	<?php endforeach; ?>
<?php else: ?>
	<?php echo template::speech('Aucune galerie.'); ?>
<?php endif; ?>