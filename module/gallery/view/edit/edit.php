<?php echo template::formOpen('galleryEditForm'); ?>
	<div class="row">
		<div class="col2">
			<?php echo template::button('galleryEditBack', [
				'class' => 'buttonGrey',
				'href' => helper::baseUrl() . $this->getUrl(0) . '/config',
				'ico' => 'left',
				'value' => 'Retour'
			]); ?>
		</div>
		<div class="col2 offset8">
			<?php echo template::submit('galleryEditSubmit'); ?>
		</div>
	</div>
	<div class="row">
		<div class="col12">
			<div class="block">
				<h4>Paramètre des images</h4>
				<div class="row">
					<div class="col5">
						<?php echo template::text('galleryEditName', [
							'label' => 'Nom',
							'value' => $this->getData(['module', $this->getUrl(0), $this->getUrl(2), 'config', 'name'])
						]); ?>
					</div>
					<div class="col4">
						<?php echo template::hidden('galleryEditDirectoryOld', [
							'value' => $this->getData(['module', $this->getUrl(0), $this->getUrl(2), 'config', 'directory']),
							'noDirty' => true // Désactivé à cause des modifications en ajax
						]); ?>
						<?php echo template::select('galleryEditDirectory', [], [
							'label' => 'Dossier cible',
							'noDirty' => true // Désactivé à cause des modifications en ajax
						]); ?>
					</div>
					<div class="col3">
						<?php echo template::select('galleryEditSort', $module::$sort, [
							'selected' => $this->getData(['module', $this->getUrl(0), $this->getUrl(2), 'config', 'sort']),
							'label' => 'Tri des images',
							'help' => 'Les images sont triées par nom de fichier grâce à la méthode naturelle qui donne de meilleurs résultats lorsque les images sont numérotées.<br>
										N\'oubliez pas d\'enregistrer pour sauvegarder le tri manuel des images'
						]); ?>	
					</div>				
				</div>
				<div class="row">
					<div class="col12">
						<?php if($module::$pictures): ?>
							<?php echo template::table([1, 4, 1, 5, 1], $module::$pictures, ['','Image', 'Couverture','Légende',''],['id' => 'galleryTable'], $module::$picturesId ); ?>
							<?php echo template::hidden('galleryEditFormResponse'); ?>
							<?php echo template::hidden('galleryEditSubmit',[
										'value' => false
									]); ?>
						<?php else: ?>
							<?php echo template::speech('Aucune image.'); ?>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</div>
	</div>

		<div class="moduleVersion">Version n°
			<?php echo $module::GALLERY_VERSION; ?>
		</div>
<?php echo template::formClose(); ?>
