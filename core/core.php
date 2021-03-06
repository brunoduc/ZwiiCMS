<?php

/**
 * This file is part of Zwii. *
 * For full copyright and license information, please see the LICENSE
 * file that was distributed with this source code.
 *
 * @author Rémi Jean <remi.jean@outlook.com>
 * @copyright Copyright (C) 2008-2018, Rémi Jean
 * @license GNU General Public License, version 3
 * @author Frédéric Tempez <frederic.tempez@outlook.com>
 * @copyright Copyright (C) 2018-2020, Frédéric Tempez
 * @link http://zwiicms.com/
 */

class common {

	const DISPLAY_RAW = 0;
	const DISPLAY_JSON = 1;
	const DISPLAY_LAYOUT_BLANK = 2;
	const DISPLAY_LAYOUT_MAIN = 3;
	const DISPLAY_LAYOUT_LIGHT = 4;
	const GROUP_BANNED = -1;
	const GROUP_VISITOR = 0;
	const GROUP_MEMBER = 1;
	const GROUP_MODERATOR = 2;
	const GROUP_ADMIN = 3;
	// Dossier de travail
	const BACKUP_DIR = 'site/backup/';
	const DATA_DIR = 'site/data/';
	const FILE_DIR = 'site/file/';
	const TEMP_DIR = 'site/tmp/';

	// Miniatures de la gallery
	const THUMBS_SEPARATOR = 'mini_';
	const THUMBS_WIDTH = 640;

	// Numéro de version 
	const ZWII_VERSION = '10.1.000';
	const ZWII_UPDATE_CHANNEL = "v10";

	public static $actions = [];
	public static $coreModuleIds = [
		'config',
		'install',
		'maintenance',
		'page',
        'search',
		'sitemap',
		'theme',
		'user'
	];
	public static $dataStage = [
		'config',
		'core',
		'module',			
		'page',
		'user',
		'theme',
		'admin'
	];
	private $data = [];
	private $hierarchy = [
		'all' => [],
		'visible' => [],
		'bar' => []
	];
	private $input = [
		'_COOKIE' => [],
		'_POST' => []
	];
	public static $inputBefore = [];
	public static $inputNotices = [];
	public static $importNotices = [];
	public static $coreNotices = [];
	public $output = [
		'access' => true,
		'content' => '',
		'contentLeft' => '',
		'contentRight' => '',
		'display' => self::DISPLAY_LAYOUT_MAIN,
		'metaDescription' => '',
		'metaTitle' => '',
		'notification' => '',
		'redirect' => '',		
		'script' => '',
		'showBarEditButton' => false,
		'showPageContent' => false,
		'state' => false,
		'style' => '',
		'title' => null, // Null car un titre peut être vide
		// Trié par ordre d'exécution
		'vendor' => [
			'jquery',
			'normalize',
			'lity',
			'filemanager',
			'flatpickr', 
			// 'tinycolorpicker', Désactivé par défaut
			// 'tinymce', Désactivé par défaut
			// 'codemirror', // Désactivé par défaut
			'tippy',
			'zwiico',
			'imagemap',
			'simplelightbox'
		],
		'view' => ''
	];
	public static $groups = [
		self::GROUP_BANNED => 'Banni',
		self::GROUP_VISITOR => 'Visiteur',
		self::GROUP_MEMBER => 'Membre',
		self::GROUP_MODERATOR => 'Éditeur',
		self::GROUP_ADMIN => 'Administrateur'
	];
	public static $groupEdits = [
		self::GROUP_BANNED => 'Banni',
		self::GROUP_MEMBER => 'Membre',
		self::GROUP_MODERATOR => 'Éditeur',
		self::GROUP_ADMIN => 'Administrateur'
	];
	public static $groupNews = [
		self::GROUP_MEMBER => 'Membre',
		self::GROUP_MODERATOR => 'Éditeur',
		self::GROUP_ADMIN => 'Administrateur'
	];
	public static $groupPublics = [
		self::GROUP_VISITOR => 'Visiteur',
		self::GROUP_MEMBER => 'Membre',
		self::GROUP_MODERATOR => 'Éditeur',
		self::GROUP_ADMIN => 'Administrateur'
	];
	public static $timezone;
	private $url = '';
	private $user = [];
	private $page = '';

	/**
	 * Constructeur commun
	 */
	public function __construct() {
		// Extraction des données http
		if(isset($_POST)) {
			$this->input['_POST'] = $_POST;
		}
		if(isset($_COOKIE)) {
			$this->input['_COOKIE'] = $_COOKIE;
		}

		// Import version 9 
		if (file_exists(self::DATA_DIR . 'core.json') === true && 
			$this->getData(['core','dataVersion']) < 10000) { 
				$keepUsers = isset($_SESSION['KEEP_USERS']) ? $_SESSION['KEEP_USERS'] : false;
				$this->importData($keepUsers);
				unset ($_SESSION['KEEP_USERS']);
				// Réinstaller htaccess		
				copy('core/module/install/ressource/.htaccess', self::DATA_DIR . '.htaccess');	
				common::$importNotices [] = "Importation réalisée avec succès" ;
				//echo '<script>window.location.replace("' .  helper::baseUrl() . $this->getData(['config','homePageId']) . '")</script>';
		}
		// Installation fraîche, initialisation des modules manquants
		// La langue d'installation par défaut est fr
		foreach (self::$dataStage as $stageId) {
			$folder = $this->dirData ($stageId, 'fr');
			if (file_exists($folder . $stageId .'.json') === false) {
				$this->initData($stageId,'fr');
				common::$coreNotices [] = $stageId ;
			}
		}	

		// Utilisateur connecté
		if($this->user === []) {
			$this->user = $this->getData(['user', $this->getInput('ZWII_USER_ID')]);
		}

		// Mise ne cache des pages
		$this->page = $this->readPageCache();

		// Construit la liste des pages parents/enfants
		if($this->hierarchy['all'] === []) {
			$pages = helper::arrayCollumn($this->getData(['page']), 'position', 'SORT_ASC');
			// Parents
			foreach($pages as $pageId => $pagePosition) {
				if(
					// Page parent
					$this->getData(['page', $pageId, 'parentPageId']) === ""
					// Ignore les pages dont l'utilisateur n'a pas accès
					AND (
						$this->getData(['page', $pageId, 'group']) === self::GROUP_VISITOR
						OR (
							$this->getUser('password') === $this->getInput('ZWII_USER_PASSWORD')
							AND $this->getUser('group') >= $this->getData(['page', $pageId, 'group'])
						)
					)
				) {
					if($pagePosition !== 0) {
						$this->hierarchy['visible'][$pageId] = [];
					}
					if($this->getData(['page', $pageId, 'block']) === 'bar') {
						$this->hierarchy['bar'][$pageId] = [];
					}
					$this->hierarchy['all'][$pageId] = [];
				}
			}
			// Enfants
			foreach($pages as $pageId => $pagePosition) {
				if(
					// Page parent
					$parentId = $this->getData(['page', $pageId, 'parentPageId'])
					// Ignore les pages dont l'utilisateur n'a pas accès
					AND (
						(
							$this->getData(['page', $pageId, 'group']) === self::GROUP_VISITOR
							AND $this->getData(['page', $parentId, 'group']) === self::GROUP_VISITOR
						)
						OR (
							$this->getUser('password') === $this->getInput('ZWII_USER_PASSWORD')
							AND $this->getUser('group') >= $this->getData(['page', $parentId, 'group'])
							AND $this->getUser('group') >= $this->getData(['page', $pageId, 'group'])
						)
					)
				) {
					if($pagePosition !== 0) {
						$this->hierarchy['visible'][$parentId][] = $pageId;
					}
					if($this->getData(['page', $pageId, 'block']) === 'bar') {
						$this->hierarchy['bar'][$pageId] = [];
					}
					$this->hierarchy['all'][$parentId][] = $pageId;
				}
			}
		}
		// Construit l'url
		if($this->url === '') {
			if($url = $_SERVER['QUERY_STRING']) {
				$this->url = $url;
			}
			else {
				$this->url = $this->getData(['config', 'homePageId']);
			}
		}

		// Mise à jour des données core
		$this->update();

		// Données de proxy
		$proxy = $this->getData(['config','proxyType']) . $this->getData(['config','proxyUrl']) . ':' . $this->getData(['config','proxyPort']);
		if (!empty($this->getData(['config','proxyUrl'])) &&
			!empty($this->getData(['config','proxyPort'])) )  {
			$context = array(
				'http' => array(
					'proxy' => $proxy,
					'request_fulluri' => true,
					'verify_peer'      => false,
					'verify_peer_name' => false,
				),
				"ssl"=>array(
				"verify_peer"=>false,
				"verify_peer_name"=>false
				)
			);
			stream_context_set_default($context);
		} 
	}

	/**
	 * Ajoute les valeurs en sortie
	 * @param array $output Valeurs en sortie
	 */
	public function addOutput($output) {
		$this->output = array_merge($this->output, $output);
	}

	/**
	 * Ajoute une notice de champ obligatoire
	 * @param string $key Clef du champ
	 */
	public function addRequiredInputNotices($key) {
		// La clef est un tableau
		if(preg_match('#\[(.*)\]#', $key, $secondKey)) {
			$firstKey = explode('[', $key)[0];
			$secondKey = $secondKey[1];
			if(empty($this->input['_POST'][$firstKey][$secondKey])) {
				common::$inputNotices[$firstKey . '_' . $secondKey] = 'Obligatoire';
			}
		}
		// La clef est une chaine
		elseif(empty($this->input['_POST'][$key])) {
			common::$inputNotices[$key] = 'Obligatoire';
		}
	}

	/**
	 * Check du token CSRF (true = bo
	 */
	public function checkCSRF() {
		return ((empty($_POST['csrf']) OR hash_equals($_SESSION['csrf'], $_POST['csrf']) === false) === false);
	}

	/**
	 * Supprime des données
	 * @param array $keys Clé(s) des données
	 */
	public function deleteData($keys) {		
		//Retourne une chaine contenant le dossier à créer
		$folder = $this->dirData ($keys[0],'fr');
		// Constructeur  JsonDB
		//require_once "core/vendor/jsondb/Dot.php";
		//require_once "core/vendor/jsondb/JsonDb.php";
		$db = new \Prowebcraft\JsonDb([
			'name' => $keys[0] . '.json',
			'dir' => $folder,
			'template' => self::TEMP_DIR . 'data.template.json'
		]);
		switch(count($keys)) {
			case 1:
				$db->delete($keys[0]);
				$db->save();
				break;
			case 2:
				$db->delete($keys[0].'.'.$keys[1]);
				$db->save();
				break;
			case 3:
				$db->delete($keys[0].'.'.$keys[1].'.'.$keys[2]);
				$db->save();				
				break;
			case 4:
				$db->delete($keys[0].'.'.$keys[1].'.'.$keys[2].'.'.$keys[3]);
				$db->save();
				break;
			case 5:
				$db->delete($keys[0].'.'.$keys[1].'.'.$keys[2].'.'.$keys[3].'.'.$keys[4]);
				$db->save();
				break;
			case 6:
				$db->delete($keys[0].'.'.$keys[1].'.'.$keys[2].'.'.$keys[3].'.'.$keys[4].'.'.$keys[5]);
				$db->save();
				break;
			case 7:
				$db->delete($keys[0].'.'.$keys[1].'.'.$keys[2].'.'.$keys[3].'.'.$keys[4].'.'.$keys[5].'.'.$keys[6]);
				$db->save();
				break;
		}
	}


	/**
	 * Récupérer une copie d'écran du site Web pour le tag image si le fichier n'existe pas
	 * En local, copie du site décran de ZwiiCMS
	 */	
	public function makeImageTag () {
		if (!file_exists(self::FILE_DIR.'source/screenshot.png'))
		{ 			
			if ( strpos(helper::baseUrl(false),'localhost') == 0 AND strpos(helper::baseUrl(false),'127.0.0.1') == 0)	{							
				$googlePagespeedData = helper::urlGetContents('https://www.googleapis.com/pagespeedonline/v2/runPagespeed?url='. helper::baseUrl(false) .'&screenshot=true');	
				if ($googlePagespeedData  !== false) {
					$googlePagespeedData = json_decode($googlePagespeedData, true);
					$screenshot = $googlePagespeedData['screenshot']['data'];
					$screenshot = str_replace(array('_','-'),array('/','+'),$screenshot);
					$data = 'data:image/jpeg;base64,'.$screenshot;
					$data = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $data));			
					file_put_contents( self::FILE_DIR.'source/screenshot.png',$data);
				}
			}
		}
	}
	


	/**
	 * Accède aux données
	 * @param array $keys Clé(s) des données
	 * @return mixed
	 */
	public function getData($keys = []) {
		
		if (count($keys) >= 1) {
			// Lecture d'une donnée de page en cache
			if ($keys[0] === 'page') {
				// Décent dans les niveaux de la variable $data
				$data = $this->page;
				foreach($keys as $key) {
					// Si aucune donnée n'existe retourne null
					if(isset($data[$key]) === false) {
						return null;
					}
					// Sinon décent dans les niveaux
					else {
						$data = $data[$key];
					}
				}
				// Retourne les données
				return $data;
			}
			//Retourne une chaine contenant le dossier à créer
			$folder = $this->dirData ($keys[0],'fr');
			// Constructeur  JsonDB
			//require_once "core/vendor/jsondb/Dot.php";
			//require_once "core/vendor/jsondb/JsonDb.php";
			$db = new \Prowebcraft\JsonDb([
				'name' => $keys[0] . '.json',
				'dir' => $folder,
				'template' => self::TEMP_DIR . 'data.template.json'
			]);
			switch(count($keys)) {
				case 1:
					$tempData = $db->get($keys[0]);
					break;				
				case 2:
					$tempData = $db->get($keys[0].'.'.$keys[1]);
					break;
				case 3:
					$tempData = $db->get($keys[0].'.'.$keys[1].'.'.$keys[2]);
					break;
				case 4:
					$tempData = $db->get($keys[0].'.'.$keys[1].'.'.$keys[2].'.'.$keys[3]);
					break;
				case 5:
					$tempData = $db->get($keys[0].'.'.$keys[1].'.'.$keys[2].'.'.$keys[3].'.'.$keys[4]);
					break;
				case 6:
					$tempData = $db->get($keys[0].'.'.$keys[1].'.'.$keys[2].'.'.$keys[3].'.'.$keys[4].'.'.$keys[5]);
					break;
				case 7:
					$tempData = $db->get($keys[0].'.'.$keys[1].'.'.$keys[2].'.'.$keys[3].'.'.$keys[4].'.'.$keys[5].'.'.$keys[6]);
					break;
			}
			return $tempData;
		}
	}


	/**
	 * Lecture des fichiers de données de page et mise ne cache
	 * @param @return string données des pages
	 */
	public function readPageCache() {
		// Trois tentatives
		for($i = 0; $i < 3; $i++) {
			$data =json_decode(file_get_contents(self::DATA_DIR.'fr/page.json'), true);
			if($data) {
				return($data);
			}
			elseif($i === 2) {
				exit('Unable to read data file.');
			}
			// Pause de 10 millisecondes
			usleep(10000);
		}
	}

	/*
	* Dummy function
	* Compatibilité des modules avec v8 et v9
	*/
	public function saveData() {
		return;
	}

	/**
	 * Accède à la liste des pages parents et de leurs enfants
	 * @param int $parentId Id de la page parent
	 * @param bool $onlyVisible Affiche seulement les pages visibles
	 * @param bool $onlyBlock Affiche seulement les pages de type barre
	 * @return array
	 */
	public function getHierarchy($parentId = null, $onlyVisible = true, $onlyBlock = false) {
		$hierarchy = $onlyVisible ? $this->hierarchy['visible'] : $this->hierarchy['all'];
		$hierarchy = $onlyBlock ? $this->hierarchy['bar'] : $hierarchy;		
		// Enfants d'un parent
		if($parentId) {
			if(array_key_exists($parentId, $hierarchy)) {
				return $hierarchy[$parentId];
			}
			else {
				return [];
			}
		}
		// Parents et leurs enfants
		else {
			return $hierarchy;
		}
	}

	/**
	 * Accède à une valeur des variables http (ordre de recherche en l'absence de type : _COOKIE, _POST)
	 * @param string $key Clé de la valeur
	 * @param int $filter Filtre à appliquer à la valeur
	 * @param bool $required Champ requis
	 * @return mixed
	 */
	public function getInput($key, $filter = helper::FILTER_STRING_SHORT, $required = false) {
		// La clef est un tableau
		if(preg_match('#\[(.*)\]#', $key, $secondKey)) {
			$firstKey = explode('[', $key)[0];
			$secondKey = $secondKey[1];
			foreach($this->input as $type => $values) {
				// Champ obligatoire
				if($required) {
					$this->addRequiredInputNotices($key);
				}
				// Check de l'existence
				// Également utile pour les checkboxs qui ne retournent rien lorsqu'elles ne sont pas cochées
				if(
					array_key_exists($firstKey, $values)
					AND array_key_exists($secondKey, $values[$firstKey])
				) {
					// Retourne la valeur filtrée
					if($filter) {
						return helper::filter($this->input[$type][$firstKey][$secondKey], $filter);
					}
					// Retourne la valeur
					else {
						return $this->input[$type][$firstKey][$secondKey];
					}
				}
			}
		}
		// La clef est une chaine
		else {
			foreach($this->input as $type => $values) {
				// Champ obligatoire				
				if($required) {
					$this->addRequiredInputNotices($key);
				}
				// Check de l'existence
				// Également utile pour les checkboxs qui ne retournent rien lorsqu'elles ne sont pas cochées
				if(array_key_exists($key, $values)) {
					// Retourne la valeur filtrée
					if($filter) {
						return helper::filter($this->input[$type][$key], $filter);
					}
					// Retourne la valeur
					else {
						return $this->input[$type][$key];
					}
				}
			}
		}
		// Sinon retourne null
		return helper::filter(null, $filter);
	}

	/**
	 * Accède à une partie l'url ou à l'url complète
	 * @param int $key Clé de l'url
	 * @return string|null
	 */
	public function getUrl($key = null) {
		// Url complète
		if($key === null) {
			return $this->url;
		}
		// Une partie de l'url
		else {
			$url = explode('/', $this->url);
			return array_key_exists($key, $url) ? $url[$key] : null;
		}
	}

	/**
	 * Accède à l'utilisateur connecté
	 * @param int $key Clé de la valeur
	 * @return string|null
	 */
	public function getUser($key) {
		if(is_array($this->user) === false) {
			return false;
		}
		elseif($key === 'id') {
			return $this->getInput('ZWII_USER_ID');
		}
		elseif(array_key_exists($key, $this->user)) {
			return $this->user[$key];
		}
		else {
			return false;
		}
	}

	/**
	 * Check qu'une valeur est transmise par la méthode _POST
	 * @return bool
	 */
	public function isPost() {
		return ($this->checkCSRF() AND $this->input['_POST'] !== []);
	}

	/**
	 * Import des données de la version 9
	 * Convertit un fichier de données data.json puis le renomme
	 */
	public function importData($keepUsers = false) {
		// Trois tentatives de lecture
		for($i = 0; $i < 3; $i++) {
			$tempData=json_decode(file_get_contents(self::DATA_DIR.'core.json'), true);
			$tempTheme=json_decode(file_get_contents(self::DATA_DIR.'theme.json'), true);
			if($tempData && $tempTheme) {
				// Backup
				rename (self::DATA_DIR.'core.json',self::DATA_DIR.'imported_core.json');
				rename (self::DATA_DIR.'theme.json',self::DATA_DIR.'imported_theme.json');
				break;
			}
			elseif($i === 2) {
				exit('Impossible de lire les données à importer.');
			}
			// Pause de 10 millisecondes
			usleep(10000);
		}

		// Dossier de langues
		if (!file_exists(self::DATA_DIR . '/fr')) {
			mkdir (self::DATA_DIR . '/fr');
		}

		// Un seul fichier pour éviter les erreurs de sauvegarde des v9
		$tempData = array_merge($tempData,$tempTheme);

		// Ecriture des données
		$this->setData(['config',$tempData['config']]);
		$this->setData(['core',$tempData['core']]);	
		$this->setData(['page',$tempData['page']]);
		$this->setData(['module',$tempData['module']]);		
		$this->setData(['theme',$tempData['theme']]);

		// Import des users sauvegardés si option active
		if ($keepUsers === false) {
			$this->setData(['user',$tempData['user']]);			
		}

		// Nettoyage du fichier de thème pour forcer une régénération
		if (file_exists(self::DATA_DIR . '/theme.css')) { // On ne sait jamais
			unlink (self::DATA_DIR . '/theme.css');
		}				
	}


	/**
	 * Génére un fichier json avec la liste des pages
	 * 
	*/
    public function pages2Json() {
    // Sauve la liste des pages pour TinyMCE
		$parents = [];
        $rewrite = (helper::checkRewrite()) ? '' : '?';
        // Boucle de recherche des pages actives
		foreach($this->getHierarchy(null,false,false) as $parentId => $childIds) {
			$children = [];
			// Exclure les barres
			if ($this->getData(['page', $parentId, 'block']) !== 'bar' ) { 
				// Boucler sur les enfants et récupérer le tableau children avec la liste des enfants
				foreach($childIds as $childId) {					
					$children [] = [ 'title' => ' » '. html_entity_decode($this->getData(['page', $childId, 'title']), ENT_QUOTES) ,
								'value'=> $rewrite.$childId
					];				
				}
				// Traitement 
				if (empty($childIds)) {						
					// Pas d'enfant, uniuement l'entrée du parent
					$parents [] = ['title' =>   html_entity_decode($this->getData(['page', $parentId, 'title']), ENT_QUOTES) ,
									'value'=> $rewrite.$parentId 		
					];	
				} else {
					// Des enfants, on ajoute la page parent en premier
					array_unshift ($children ,  ['title' => html_entity_decode($this->getData(['page', $parentId, 'title']), ENT_QUOTES) ,
									'value'=> $rewrite.$parentId 		
					]);	
					// puis on ajoute les enfants au parent
					$parents [] = ['title' => html_entity_decode($this->getData(['page', $parentId, 'title']), ENT_QUOTES) ,
									'value'=> $rewrite.$parentId ,  
									'menu' => $children 
					];							
				} 											
			}
		}
        // Sitemap et Search
        $children = [];
        $children [] = ['title'=>'Rechercher dans le site',
           'value'=>$rewrite.'search'
          ];
        $children [] = ['title'=>'Plan du site',
           'value'=>$rewrite.'sitemap'
          ];
        $parents [] = ['title' => 'Pages spéciales',
                      'value' => '#',
                      'menu' => $children
                      ];
		
		// Enregistrement : 3 tentatives
		for($i = 0; $i < 3; $i++) {
			if (file_put_contents ('core/vendor/tinymce/link_list.json', json_encode($parents), LOCK_EX) !== false) {
				break;
			}
			// Pause de 10 millisecondes
			usleep(10000);
		}	
	}

	/**
	 * Retourne une chemin localisé pour l'enregistrement des données
	 * @param $stageId nom du module
	 * @param $lang langue des pages
	 * @return string du dossier à créer
	 */
	public function dirData($id, $lang) {
		// Sauf pour les pages et les modules
		if ($id === 'page' ||
			$id === 'module') {
				$folder = self::DATA_DIR . $lang . '/' ;
		} else {
			$folder = self::DATA_DIR;
		}
		return ($folder);
	}

	/**
	 * Génére un fichier robots.txt à l'installation
	 * Si le fichier exite déjà les commandes sont ajoutées
	 */
	 public function createRobots() {

		$robotValue = 
						PHP_EOL .
						'# ZWII CONFIG ---------' . PHP_EOL .
						'User-agent: *' . PHP_EOL .
						'Allow: /site/file/' .PHP_EOL .						
						'Disallow: /site/' .PHP_EOL .
						'Sitemap: ' . helper::baseUrl(false) . 'sitemap.xml' . PHP_EOL .
						'Sitemap: ' . helper::baseUrl(false) . 'sitemap.xml.gz' . PHP_EOL .
						'# ZWII CONFIG  ---------' . PHP_EOL ;

		if (file_exists('robots.txt')) {			
			return(file_put_contents(
				'robots.txt',
				$robotValue,
				FILE_APPEND
				));
		} else  {
			// Sinon on crée un fichier
			return(file_put_contents(
				'robots.txt',
				 $robotValue
				));
		}
	 }


	/**
	 * Génére un fichier un fchier sitemap.xml
	 * https://github.com/icamys/php-sitemap-generator
	 * $command valeurs possible
	 * all : génére un site map complet 
	 * Sinon contient id de la page à créer
	*/

	public function createSitemap($command = "all") {

		//require_once "core/vendor/sitemap/SitemapGenerator.php";

		$timezone = $this->getData(['config','timezone']);

		$sitemap = new \Icamys\SitemapGenerator\SitemapGenerator(helper::baseurl());

		// will create also compressed (gzipped) sitemap
		$sitemap->createGZipFile = true;

		// determine how many urls should be put into one file
		// according to standard protocol 50000 is maximum value (see http://www.sitemaps.org/protocol.html)
		$sitemap->maxURLsPerSitemap = 50000;

		// sitemap file name
		$sitemap->sitemapFileName = "sitemap.xml";
		
		$datetime = new DateTime(date('c'));
		$datetime->format(DateTime::ATOM); // Updated ISO8601
		// sitemap index file name
		$sitemap->sitemapIndexFileName = "sitemap-index.xml";
		foreach($this->getHierarchy(null, null, null) as $parentPageId => $childrenPageIds) {
			// Exclure les barres et les pages non publiques et les pages masquées
			if ($this->getData(['page',$parentPageId,'group']) !== 0  || 
				$this->getData(['page', $parentPageId, 'block']) === 'bar' )  {
				continue;
			}
			// Page désactivée, traiter les sous-pages sans prendre en compte la page parente.
			if ($this->getData(['page', $parentPageId, 'disable']) !== true ) {
				$sitemap->addUrl ($parentPageId,$datetime);
			}
			// Sous-pages
			foreach($childrenPageIds as $childKey) {
				if ($this->getData(['page',$childKey,'group']) !== 0 || $this->getData(['page', $childKey, 'disable']) === true)  {
					continue;
				}
				$sitemap->addUrl($childKey,$datetime);

				// La sous-page est un blog
				if ($this->getData(['page', $childKey, 'moduleId']) === 'blog' &&
				   !empty($this->getData(['module',$childKey])) ) {
					foreach($this->getData(['module',$childKey]) as $articleId => $article) {
						if($this->getData(['module',$childKey,$articleId,'state']) === true) {
							$date = $this->getData(['module',$childKey,$articleId,'publishedOn']);
							$sitemap->addUrl( $childKey . '/' . $articleId , new DateTime("@{$date}",new DateTimeZone($timezone)));
						}			
					}
				}							
			}
			// Articles du blog
			if ($this->getData(['page', $parentPageId, 'moduleId']) === 'blog' &&
				!empty($this->getData(['module',$parentPageId])) ) {				
				foreach($this->getData(['module',$parentPageId]) as $articleId => $article) {
					if($this->getData(['module',$parentPageId,$articleId,'state']) === true) {		
						$date = $this->getData(['module',$parentPageId,$articleId,'publishedOn']);
						$sitemap->addUrl( $parentPageId . '/' . $articleId , new DateTime("@{$date}",new DateTimeZone($timezone)));
					}
				}
			}
		}		
		
		// generating internally a sitemap
		$sitemap->createSitemap();

		// writing early generated sitemap to file
		$sitemap->writeSitemap();
		
		return(file_exists('sitemap.xml'));

	}
	
	/*
	* Création d'une miniature
	* Fonction utilisée lors de la mise à jour d'une version 9 à une version 10
	* @param string $src image source
	* @param string $dets image destination
	* @param integer $desired_width largeur demandée
	*/
	function makeThumb($src, $dest, $desired_width) {
		// Vérifier l'existence du dossier de destination.
		$fileInfo = pathinfo($dest);
		if (!is_dir($fileInfo['dirname'])) {
			mkdir($fileInfo['dirname'],0755,true);
		}
		// Type d'image
		switch(	$fileInfo['extension']) {
			case 'jpeg':
			case 'jpg':
				$source_image = imagecreatefromjpeg($src);
				break;
			case 'png':
				$source_image = imagecreatefrompng($src);
				break;
			case 'gif':
				$source_image = imagecreatefromgif($src);
				break;
		}
		// Image valide
		if ($source_image) {
			$width = imagesx($source_image);
			$height = imagesy($source_image);		
			/* find the "desired height" of this thumbnail, relative to the desired width  */
			$desired_height = floor($height * ($desired_width / $width));		
			/* create a new, "virtual" image */
			$virtual_image = imagecreatetruecolor($desired_width, $desired_height);		
			/* copy source image at a resized size */
			imagecopyresampled($virtual_image, $source_image, 0, 0, 0, 0, $desired_width, $desired_height, $width, $height);		
			switch(mime_content_type($src) ) {
				case 'image/jpeg':
				case 'image/jpg':
					return (imagejpeg($virtual_image, $dest));
					break;
				case 'image/png':
					return (imagepng($virtual_image, $dest));
					break;
				case 'image/gif':
					return (imagegif($virtual_image, $dest));
					break;
			}			
		} else {
			return (false);
		}
	}


	/**
	 * Envoi un mail
	 * @param string|array $to Destinataire
	 * @param string $subject Sujet
	 * @param string $content Contenu
	 * @return bool
	 */
	public function sendMail($to, $subject, $content, $replyTo = null) {


		// Layout
		ob_start();
		include 'core/layout/mail.php';		
		$layout = ob_get_clean();
		$mail = new PHPMailer\PHPMailer\PHPMailer;
		$mail->CharSet = 'UTF-8';					
		// Mail
		try{
			// Paramètres SMTP
			if ($this->getdata(['config','smtp','enable'])) {
				//$mail->SMTPDebug = PHPMailer\PHPMailer\SMTP::DEBUG_SERVER;  
				$mail->isSMTP();
				$mail->SMTPAutoTLS = false;
				$mail->Host = $this->getdata(['config','smtp','host']);
				$mail->Port = (int) $this->getdata(['config','smtp','port']);
				if ($this->getData(['config','smtp','auth'])) {					
					$mail->Username = $this->getData(['config','smtp','username']);
					$mail->Password = helper::decrypt($this->getData(['config','smtp','username']),$this->getData(['config','smtp','password']));
					$mail->SMTPAuth = $this->getData(['config','smtp','auth']);
					$mail->SMTPSecure = $this->getData(['config','smtp','secure']);
					$mail->setFrom($this->getData(['config','smtp','username']));
					if (is_null($replyTo)) {
						$mail->addReplyTo($this->getData(['config','smtp','username']));
					} else {
						$mail->addReplyTo($replyTo);
					}
				}
			// Fin SMTP
			} else {
				$host = str_replace('www.', '', $_SERVER['HTTP_HOST']);
				$mail->setFrom('no-reply@' . $host, $this->getData(['config', 'title']));						
				if (is_null($replyTo)) {
					$mail->addReplyTo('no-reply@' . $host, $this->getData(['config', 'title']));
				} else {
					$mail->addReplyTo($replyTo);
				}				
			}
			if(is_array($to)) {
					foreach($to as $userMail) {
							$mail->addAddress($userMail);
					}
			}
			else {
					$mail->addAddress($to);
			}
			$mail->isHTML(true);
			$mail->Subject = $subject;
			$mail->Body = $layout;
			$mail->AltBody = strip_tags($content);
			if($mail->send()) {
					return true;
			}
			else {
					return $mail->ErrorInfo;
			}
		} catch (phpmailerException $e) {
			return $e->errorMessage();
		} catch (Exception $e) {
			return $e->getMessage();
		}
	}

	/**
	 * Sauvegarde des données
	 * @param array $keys Clé(s) des données
	 */
	public function setData($keys = []) {

		// Pas d'enregistrement lorsque'une notice est présente
		if (!empty(self::$inputNotices)) {
			return false;
		}
	
		//Retourne une chaine contenant le dossier à créer
		$folder = $this->dirData ($keys[0],'fr');
		// Constructeur  JsonDB
		$db = new \Prowebcraft\JsonDb([
			'name' => $keys[0] . '.json',
			'dir' => $folder,
			'template' => self::TEMP_DIR . 'data.template.json'
		]);

		switch(count($keys)) {			
			case 2:
				$db->set($keys[0],$keys[1]);
				$db->save();
				break;
			case 3:
				$db->set($keys[0].'.'.$keys[1],$keys[2]);
				$db->save();				
				break;
			case 4:
				$db->set($keys[0].'.'.$keys[1].'.'.$keys[2],$keys[3]);
				$db->save();
				break;
			case 5:
				$db->set($keys[0].'.'.$keys[1].'.'.$keys[2].'.'.$keys[3],$keys[4]);
				$db->save();
				break;
			case 6:
				$db->set($keys[0].'.'.$keys[1].'.'.$keys[2].'.'.$keys[3].'.'.$keys[4],$keys[5]);
				$db->save();
				break;
			case 7:
				$db->set($keys[0].'.'.$keys[1].'.'.$keys[2].'.'.$keys[3].'.'.$keys[4].'.'.$keys[5],$keys[6]);
				$db->save();
				break;
		}
		return true;
	}

	/**
	 * Initialisation des données
	 * @param array $module : nom du module à générer 
	 * choix valides :  core config user theme page module
	 */ 
	public function initData($module, $lang = 'fr', $sampleSite = false) {
		
		// Tableau avec les données vierges
		require_once('core/module/install/ressource/defaultdata.php'); 

		// Stockage dans un sous-dossier localisé
		// Le dossier de langue existe t-il ?
		if (!file_exists(self::DATA_DIR . '/' . $lang)) {
			mkdir (self::DATA_DIR . '/' . $lang);
		}
		$folder = $this->dirData ($module,$lang);
		// Constructeur  JsonDB
		//require_once "core/vendor/jsondb/Dot.php";
		//require_once "core/vendor/jsondb/JsonDb.php";
		$db = new \Prowebcraft\JsonDb([
			'name' => $module . '.json',
			'dir' => $folder,
			'template' => self::TEMP_DIR . 'data.template.json'
		]);
		if ($sampleSite === true) {
			$db->set($module,init::$siteData[$module]);
		} else {
			$db->set($module,init::$defaultData[$module]);
		}
		
		$db->save;
	}


	/**
	 * Mises à jour
	 */
	private function update() {
	
		// Version 9.0.0
		if($this->getData(['core', 'dataVersion']) < 9000) {
			$this->deleteData(['theme', 'site', 'block']);
			if ($this->getData(['theme','menu','position']) === 'body-top') {
				$this->setData(['theme','menu','position','top']);
			}
			$this->setData(['theme', 'menu','fixed',false]);						
			$this->setData(['core', 'dataVersion', 9000]);
			//$this->SaveData();
		}	
		// Version 9.0.01
		if($this->getData(['core', 'dataVersion']) < 9001) {
			$this->deleteData(['config', 'social', 'googleplusId']);
			$this->setData(['core', 'dataVersion', 9001]);
			//$this->SaveData();
		}
		// Version 9.0.08
		if($this->getData(['core', 'dataVersion']) < 9008) {
			$this->setData(['theme', 'footer', 'textTransform','none']);
			$this->setData(['theme', 'footer', 'fontWeight','normal']);
			$this->setData(['theme', 'footer', 'fontSize','.8em']);
			$this->setData(['theme', 'footer', 'font','Open+Sans']);	
			$this->setData(['core', 'dataVersion', 9008]);
			//$this->SaveData();
		}
		// Version 9.0.09
		if($this->getData(['core', 'dataVersion']) < 9009) {
			$this->setData(['core', 'dataVersion', 9009]);
			//$this->SaveData();
		}
		// Version 9.0.10
		if($this->getData(['core', 'dataVersion']) < 9010) {
			$this->deleteData(['config', 'social', 'googleplusId']);			
			$this->setData(['core', 'dataVersion', 9010]);
			//$this->SaveData();
		}
		// Version 9.0.11
		if($this->getData(['core', 'dataVersion']) < 9011) {
			if ($this->getData(['theme','menu','position']) === 'body')
				$this->setData(['theme','menu','position','site']);
			$this->setData(['core', 'dataVersion', 9011]);
			//$this->SaveData();
		}
		// Version 9.0.17
		if($this->getData(['core', 'dataVersion']) < 9017) {
			$this->setData(['theme','footer','displayVersion', true ]);
			$this->setData(['core', 'dataVersion', 9017]);
			//$this->SaveData();
		}
		// Version 9.1.0
		if($this->getData(['core', 'dataVersion']) < 9100) {
			$this->setData(['theme','footer','displayVersion', true ]);
			$this->setData(['theme','footer','displaySiteMap', true ]);
			$this->setData(['theme','footer','displayCopyright', false ]);
			$this->setData(['core', 'dataVersion', 9100]);
			//$this->SaveData();
		}
		// Version 9.2.00
		if($this->getData(['core', 'dataVersion']) < 9200) {
			$this->setData(['theme','footer','template', 3 ]);
			$this->setData(['theme','footer','margin', true ]);			
			$this->setData(['theme','footer','displayLegal', !empty($this->getdata(['config','legalPageId'])) ]);
			$this->setData(['theme','footer','displaySearch', false ]);
			$this->setData(['config','social','githubId', '' ]);
			$this->setData(['core', 'dataVersion', 9200]);
			//$this->SaveData();
		}
		// Version 9.2.05
		if($this->getData(['core', 'dataVersion']) < 9205) {
			// Nettoyage Swiper
			if (file_exists('core/vendor/tinymce/templates/swiper.html')) {
				unlink ('core/vendor/tinymce/templates/swiper.html');
			}
			if (is_dir('core/vendor/swiper')) {
				$dir = getcwd();
				chdir('core/vendor/swiper');
				$files = glob('*');
				foreach($files as $file) unlink($file);
				chdir($dir);
				rmdir ('core/vendor/swiper/');
			}
			$this->setData(['core', 'dataVersion', 9205]);
			//$this->SaveData();
		}
		// Version 9.2.10
		if($this->getData(['core', 'dataVersion']) < 9210) {
			
			// Utile pour l'installation d'un backup sur un autre serveur
			//$this->setData(['core', 'baseUrl', helper::baseUrl(false,false) ]);

			// Suppression d'une option de hauteur de la bannière
			if ($this->getData(['theme', 'header','height']) === 'none') {
				$this->setData(['theme', 'header','height','150px']);
			}	
			// Changer le nom de la clé linkHome -> linkHomePage
			$this->setdata(['theme','header','linkHomePage',$this->getData(['theme','header','linkHome'])]);
			$this->deleteData(['theme','header','linkHome']);

			// Préparation des clés de légendes pour la v10
			// Construire une liste plate de parents et d'enfants

			$pageList = array();

			foreach ($this->getHierarchy(null,null,null) as $parentKey=>$parentValue) {
				$pageList [] = $parentKey;
				foreach ($parentValue as $childKey) {
					$pageList [] = $childKey;
				}
			}			
			// Parcourir toutes les pages
			foreach ($pageList as $parentKey => $parent) {
				//La page a une galerie
				if ($this->getData(['page',$parent,'moduleId']) === 'gallery' ) {
					// Lire les données du module
					// Parcourir les dossiers de la galerie
					$tempData =  $this->getData(['module', $parent]);			
					foreach ($tempData as $galleryKey => $galleryItem) {
						foreach ($galleryItem as $legendKey => $legendValue) {
							// Recherche la clé des légendes
							if ($legendKey === 'legend') {
								foreach ($legendValue as $itemKey=>$itemValue) {		
									// Ancien nom avec un point devant l'extension ?
									if (strpos($itemKey,'.') > 0) {
										// Créer une nouvelle clé
										$this->setData(['module', $parent, $galleryKey, 'legend',str_replace('.','',$itemKey),$itemValue]);
										// Supprimer la valeur
										$this->deleteData(['module', $parent, $galleryKey, 'legend',$itemKey]);
									}									
								}
							}
						}
					}
				}
			}			
			$this->setData(['core', 'dataVersion', 9210]);
		}
		// Version 9.2.11
		if($this->getData(['core', 'dataVersion']) < 9211) {
			$autoUpdate= mktime(0, 0, 0);
			$this->setData(['core', 'lastAutoUpdate', $autoUpdate]);
			$this->setData(['config','autoUpdate', true]);
			$this->setData(['core', 'dataVersion', 9211]);
		}
		// Version 9.2.12
		if($this->getData(['core', 'dataVersion']) < 9212) {
			$this->setData(['theme','menu', 'activeColorAuto',true]);
			$this->setData(['theme','menu', 'activeColor','rgba(255, 255, 255, 1)']);
			$this->setData(['core', 'dataVersion', 9212]);
			//$this->SaveData();
		}
		// Version 9.2.15
		if($this->getData(['core', 'dataVersion']) < 9215) {
			// Données de la barre de langue dans le menu
			$this->setData(['theme','menu','burgerTitle',true]);
			$this->setData(['core', 'dataVersion', 9215]);
		}	
		// Version 9.2.16
		if($this->getData(['core', 'dataVersion']) < 9216) {
			// Utile pour l'installation d'un backup sur un autre serveur
			// mais avec la réécriture d'URM
			$this->setData(['core', 'baseUrl', helper::baseUrl(true,false) ]);
			$this->setData(['core', 'dataVersion', 9216]);
		}
		// Version 9.2.21
		if($this->getData(['core', 'dataVersion']) < 9221) {
			// Utile pour l'installation d'un backup sur un autre serveur
			// mais avec la réécriture d'URM
			$this->setData(['theme', 'body', 'toTopbackgroundColor', 'rgba(33, 34, 35, .8)' ]);
			$this->setData(['theme', 'body', 'toTopColor', 'rgba(255, 255, 255, 1)' ]);
			$this->setData(['core', 'dataVersion', 9221]);
		}		
		// Version 9.2.23
		if($this->getData(['core', 'dataVersion']) < 9223) {
			// Utile pour l'installation d'un backup sur un autre serveur
			// mais avec la réécriture d'URM
			$this->setData(['config', 'proxyUrl', '' ]);
			$this->setData(['config', 'proxyPort', '' ]);
			$this->setData(['config', 'proxyType', 'tcp://' ]);
			$this->setData(['core', 'dataVersion', 9223]);
		}	
		// Version 9.2.27
		if($this->getData(['core', 'dataVersion']) < 9227) {
			// Forcer la régénération du thème
			if (file_exists(self::DATA_DIR.'theme.css')) {
				unlink (self::DATA_DIR.'theme.css');
			}		
			$this->setData(['core', 'dataVersion', 9227]);
		}			
		// Version 10.0.00
		if($this->getData(['core', 'dataVersion']) < 10000) {
			$this->setData(['config', 'faviconDark','faviconDark.ico']);

			//----------------------------------------
			// Mettre à jour les données des galeries
			$pageList = array();
			foreach ($this->getHierarchy(null,null,null) as $parentKey=>$parentValue) {
				$pageList [] = $parentKey;
				foreach ($parentValue as $childKey) {
					$pageList [] = $childKey;
				}
			}	
			// Mise à jour des données pour la galerie v2
			foreach ($pageList as $parentKey => $parent) {
				//La page a une galerie
				if ($this->getData(['page',$parent,'moduleId']) === 'gallery' ) {
					// Parcourir les dossiers de la galerie
					$tempData =  $this->getData(['module', $parent]);	
					$i = 1;
					foreach ($tempData as $galleryKey => $galleryItem) {
						// Ordre de tri des galeries
						if ( $this->getdata(['module',$parent,$galleryKey,'config','sort']) === NULL)  {
							$this->setdata(['module',$parent,$galleryKey,'config','sort','SORT_ASC']);
						}
						// Position de la galerie, tri manuel
						if ( $this->getdata(['module',$parent,$galleryKey,'config','position']) === NULL) {
							$this->setdata(['module',$parent,$galleryKey,'config','position',$i++]);
						}						
						// Positions des images, tri manuel
						if ( $this->getdata(['module',$parent,$galleryKey,'positions']) === NULL) {
							$c = count($this->getdata(['module',$parent,$galleryKey,'legend']));
							$this->setdata(['module',$parent,$galleryKey,'positions', range(0,$c-1) ]);
						}
						// Image de couverture
						if ( $this->getdata(['module',$parent,$galleryKey,'config','homePicture']) === NULL)  {
							if (is_dir($this->getdata(['module',$parent,$galleryKey,'config','directory']))) {
								$iterator = new DirectoryIterator($this->getdata(['module',$parent,$galleryKey,'config','directory']));
								foreach($iterator as $fileInfos) {
									if($fileInfos->isDot() === false AND $fileInfos->isFile() AND @getimagesize($fileInfos->getPathname())) {									
										$this->setdata(['module',$parent,$galleryKey,'config','homePicture',$fileInfos->getFilename()]);
										break;
									}
								}
							}
						}	
					}			
				}
			}	
			// Contrôle des options php.ini pour la mise à jour auto
			if (helper::urlGetContents('http://zwiicms.com/update/' . common::ZWII_UPDATE_CHANNEL . '/version') ===  false) {
				$this->setData(['config','autoUpdate',false]);
			}

			$this->setData(['core', 'dataVersion', 10000]);	
		}
		// Version 10.0.092
		if ($this->getData(['core', 'dataVersion']) < 10092) {
			// Suppression du dossier fullpage
			if (is_dir('core/vendor/fullpage')) {
				$dir = getcwd();
				chdir('core/vendor/fullpage');
				$files = glob('*');
				foreach($files as $file) unlink($file);
				chdir($dir);
				rmdir ('core/vendor/fullpage/');
			}
			if (file_exists('core/vendor/tinymce/templates/fullPageSections.html')) {
				unlink ('core/vendor/tinymce/templates/fullPageSections.html'); }
			if (file_exists('core/vendor/tinymce/templates/fullPageSlides.html')) {
				unlink ('core/vendor/tinymce/templates/fullPageSlides.html'); }
			$this->setData(['core', 'dataVersion', 10092]);
		}
		// Version 10.0.093
		if ($this->getData(['core', 'dataVersion']) < 10093) {
			// Déplacement du fichier admin.css dans data
			if (file_exists('core/layout/admin.css')) {
				copy('core/layout/admin.css',self::DATA_DIR.'admin.css'); 
				unlink('core/layout/admin.css');
			}	
			//Déplacement d'un fichier de ressources
			if (file_exists('core/module/config/ressource/.htaccess'))	{
				unlink('core/module/config/ressource/.htaccess');
				rmdir ('core/module/config/ressource');
			}
			$this->setData(['core', 'dataVersion', 10093]);
			// Réorganisation du thème
			$this->setData(['theme','text','linkTextColor',$this->getData(['theme','link', 'textColor'])]);
		}

	}
}

class core extends common {

	/**
	 * Constructeur du coeur
	 */
	public function __construct() {
		parent::__construct();
		// Token CSRF
		if(empty($_SESSION['csrf'])) {
			$_SESSION['csrf'] = bin2hex(openssl_random_pseudo_bytes(32));
		}
		// Fuseau horaire
		self::$timezone = $this->getData(['config', 'timezone']); // Utile pour transmettre le timezone à la classe helper
		date_default_timezone_set(self::$timezone);
		// Supprime les fichiers temporaires
		$lastClearTmp = mktime(0, 0, 0);
		if($lastClearTmp > $this->getData(['core', 'lastClearTmp']) + 86400) {
			$iterator = new DirectoryIterator(self::TEMP_DIR);
			foreach($iterator as $fileInfos) {
				if( $fileInfos->isFile() && 
					$fileInfos->getBasename() !== '.htaccess' &&
					$fileInfos->getBasename() !== '.gitkeep'
				) {
					@unlink($fileInfos->getPathname());
				}
			}
			// Date de la dernière suppression
			$this->setData(['core', 'lastClearTmp', $lastClearTmp]);
			// Enregistre les données
			//$this->SaveData();
		}
		// Backup automatique des données
		$lastBackup = mktime(0, 0, 0);
		if(
			$this->getData(['config', 'autoBackup'])
			AND $lastBackup > $this->getData(['core', 'lastBackup']) + 86400
			AND $this->getData(['user']) // Pas de backup pendant l'installation
		) {
			// Copie des fichier de données
			helper::autoBackup(self::BACKUP_DIR,['backup','tmp','file']);
			// Date du dernier backup
			$this->setData(['core', 'lastBackup', $lastBackup]);
			// Supprime les backups de plus de 30 jours
			$iterator = new DirectoryIterator(self::BACKUP_DIR);
			foreach($iterator as $fileInfos) {
				if(
					$fileInfos->isFile()
					AND $fileInfos->getBasename() !== '.htaccess'
					AND $fileInfos->getMTime() + (86400 * 30) < time()
				) {
					@unlink($fileInfos->getPathname());
				}
			}
		}
		// Crée le fichier de personnalisation avancée
		if(file_exists(self::DATA_DIR.'custom.css') === false) {
			file_put_contents(self::DATA_DIR.'custom.css', file_get_contents('core/module/theme/resource/custom.css'));
			chmod(self::DATA_DIR.'custom.css', 0755);
		}
		// Crée le fichier de personnalisation
		if(file_exists(self::DATA_DIR.'theme.css') === false) {
			file_put_contents(self::DATA_DIR.'theme.css', '');
			chmod(self::DATA_DIR.'theme.css', 0755);
		}
		// Crée le fichier de personnalisation de l'administration
		if(file_exists(self::DATA_DIR.'admin.css') === false) {
			file_put_contents(self::DATA_DIR.'admin.css', '');
			chmod(self::DATA_DIR.'admin.css', 0755);
		}		
		// Check la version rafraichissement du theme
		$cssVersion = preg_split('/\*+/', file_get_contents(self::DATA_DIR.'theme.css'));
		if(empty($cssVersion[1]) OR $cssVersion[1] !== md5(json_encode($this->getData(['theme'])))) {
			// Version
			$css = '/*' . md5(json_encode($this->getData(['theme']))) . '*/';
			// Import des polices de caractères
			$css .= '@import url("https://fonts.googleapis.com/css?family=' . $this->getData(['theme', 'text', 'font']) . '|' . $this->getData(['theme', 'title', 'font']) . '|' . $this->getData(['theme', 'header', 'font']) .  '|' . $this->getData(['theme', 'menu', 'font']) . '");';
			// Fond du site
			$colors = helper::colorVariants($this->getData(['theme', 'body', 'backgroundColor']));
			$css .= 'body,div.mce-edit-area{background-color:' . $colors['normal'] . ';font-family:"' . str_replace('+', ' ', $this->getData(['theme', 'text', 'font'])) . '",sans-serif}';
			// Fond TinyMCe
			$css .= 'div.mce-edit-area{background-color:' . $colors['normal'] . ' !important}';
			if($themeBodyImage = $this->getData(['theme', 'body', 'image'])) {
				$css .= 'body,div.mce-edit-area{background-image:url("../file/source/' . $themeBodyImage . '");background-position:' . $this->getData(['theme', 'body', 'imagePosition']) . ';background-attachment:' . $this->getData(['theme', 'body', 'imageAttachment']) . ';background-size:' . $this->getData(['theme', 'body', 'imageSize']) . ';background-repeat:' . $this->getData(['theme', 'body', 'imageRepeat']) . '}';
				$css .= 'div.mce-edit-area{background-image:url("../file/source/' . $themeBodyImage . '") !important;background-position:' . $this->getData(['theme', 'body', 'imagePosition']) . ';background-attachment:' . $this->getData(['theme', 'body', 'imageAttachment']) . ';background-size:' . $this->getData(['theme', 'body', 'imageSize']) . ';background-repeat:' . $this->getData(['theme', 'body', 'imageRepeat']) . '}';
			}
			// Icône BacktoTop
			$css .= '#backToTop {background-color:' .$this->getData(['theme', 'body', 'toTopbackgroundColor']). ';color:'.$this->getData(['theme', 'body', 'toTopColor']).';}';
			// Site
			$colors = helper::colorVariants($this->getData(['theme', 'text', 'linkTextColor']));
			$css .= 'a{color:' . $colors['normal'] . '}';
			$css .= 'a:hover{color:' . $colors['darken'] . '}';
			$css .= 'body,.row > div{font-size:' . $this->getData(['theme', 'text', 'fontSize']) . '}';
			$css .= 'body,.block h4{color:' . $this->getData(['theme', 'text', 'textColor']) . '}';
			$css .= 'select,input[type=\'email\'],input[type=\'text\'],textarea{color:' . $this->getData(['theme', 'text', 'backgroundColor']) . '}';
			// Couleur fixée dans admin.css
			//$css .= '.button.buttonGrey,.button.buttonGrey:hover{color:' . $this->getData(['theme', 'text', 'textColor']) . '}';			
			$css .= '.container{max-width:' . $this->getData(['theme', 'site', 'width']) . '}';
			$margin = $this->getData(['theme', 'site', 'margin']) ? '0' : '20px';			
			$css .= $this->getData(['theme', 'site', 'width']) === '100%' ? '#site{margin:0 auto !important;} body{margin:0 auto !important;}  #bar{margin:0 auto !important;} body > header{margin:0 auto !important;} body > nav {margin: 0 auto !important;} body > footer {margin:0 auto !important;}': "#site{margin: " . $margin . " auto !important;} body{margin:0px 10px;}  #bar{margin: 0 -10px;} body > header{margin: 0 -10px;} body > nav {margin: 0 -10px;} body > footer {margin: 0 -10px;} ";
			$css .= $this->getData(['theme', 'site', 'width']) === '750px' ? '.button, button{font-size:0.8em;}' : '';
			$css .= '#site{background-color:' . $this->getData(['theme', 'site', 'backgroundColor']) . ';border-radius:' . $this->getData(['theme', 'site', 'radius']) . ';box-shadow:' . $this->getData(['theme', 'site', 'shadow']) . ' #212223;}';
			$css .= '.editorWysiwyg {background-color:' . $this->getData(['theme', 'site', 'backgroundColor']) . ';}';
			$colors = helper::colorVariants($this->getData(['theme', 'button', 'backgroundColor']));
			$css .= '.speechBubble,.button,.button:hover,button[type=\'submit\'],.pagination a,.pagination a:hover,input[type=\'checkbox\']:checked + label:before,input[type=\'radio\']:checked + label:before,.helpContent{background-color:' . $colors['normal'] . ';color:' . $colors['text'] . '}';
			$css .= '.helpButton span{color:' . $colors['normal'] . '}';
			$css .= 'input[type=\'text\']:hover,input[type=\'password\']:hover,.inputFile:hover,select:hover,textarea:hover{border-color:' . $colors['normal'] . '}';
			$css .= '.speechBubble:before{border-color:' . $colors['normal'] . ' transparent transparent transparent}';
			$css .= '.button:hover,button[type=\'submit\']:hover,.pagination a:hover,input[type=\'checkbox\']:not(:active):checked:hover + label:before,input[type=\'checkbox\']:active + label:before,input[type=\'radio\']:checked:hover + label:before,input[type=\'radio\']:not(:checked):active + label:before{background-color:' . $colors['darken'] . '}';
			$css .= '.helpButton span:hover{color:' . $colors['darken'] . '}';
			$css .= '.button:active,button[type=\'submit\']:active,.pagination a:active{background-color:' . $colors['veryDarken'] . '}';					
			$colors = helper::colorVariants($this->getData(['theme', 'title', 'textColor']));
			$css .= 'h1,h2,h3,h4,h5,h6{color:' . $colors['normal'] . ';font-family:"' . str_replace('+', ' ', $this->getData(['theme', 'title', 'font'])) . '",sans-serif;font-weight:' . $this->getData(['theme', 'title', 'fontWeight']) . ';text-transform:' . $this->getData(['theme', 'title', 'textTransform']) . '}';
			// Bannière
			$colors = helper::colorVariants($this->getData(['theme', 'header', 'backgroundColor']));
			if($this->getData(['theme', 'header', 'margin'])) {
				if($this->getData(['theme', 'menu', 'position']) === 'site-first') {
					$css .= 'header{margin:0 20px}';
				}
				else {
					$css .= 'header{margin:20px 20px 0 20px}';
				}
			}
			$css .= 'header{background-size:' . $this->getData(['theme','header','imageContainer']).'}';
			$css .= 'header{background-color:' . $colors['normal'];

			// Valeur de hauteur traditionnelle
			$css .= ';height:' . $this->getData(['theme', 'header', 'height']) . ';line-height:' . $this->getData(['theme', 'header', 'height']) ;

			$css .=  ';text-align:' . $this->getData(['theme', 'header', 'textAlign']) . '}';
			if($themeHeaderImage = $this->getData(['theme', 'header', 'image'])) {
				$css .= 'header{background-image:url("../file/source/' . $themeHeaderImage . '");background-position:' . $this->getData(['theme', 'header', 'imagePosition']) . ';background-repeat:' . $this->getData(['theme', 'header', 'imageRepeat']) . '}';
			}
			$colors = helper::colorVariants($this->getData(['theme', 'header', 'textColor']));
			$css .= 'header span{color:' . $colors['normal'] . ';font-family:"' . str_replace('+', ' ', $this->getData(['theme', 'header', 'font'])) . '",sans-serif;font-weight:' . $this->getData(['theme', 'header', 'fontWeight']) . ';font-size:' . $this->getData(['theme', 'header', 'fontSize']) . ';text-transform:' . $this->getData(['theme', 'header', 'textTransform']) . '}';
			// Menu
			$colors = helper::colorVariants($this->getData(['theme', 'menu', 'backgroundColor']));							
			$css .= 'nav,nav a{background-color:' . $colors['normal'] . '}';
			$css .= 'nav a,#toggle span,nav a:hover{color:' . $this->getData(['theme', 'menu', 'textColor']) . '}';
			$css .= 'nav a:hover{background-color:' . $colors['darken'] . '}';
			$css .= 'nav a.active{color:' . $this->getData(['theme','menu','activeTextColor']) . ';}';
			if ($this->getData(['theme','menu','activeColorAuto']) === true) {
				$css .= 'nav a.active{background-color:' . $colors['veryDarken'] . '}';				
			} else {
				$css .= 'nav a.active{background-color:' . $this->getData(['theme','menu','activeColor']) . '}';
				/*$color2 = helper::colorVariants($this->getData(['theme', 'menu', 'textColor']));
				$css .= 'nav a.active{color:' .  $color2['text'] . '}';*/
			}		
			$css .= 'nav #burgerText{color:' .  $colors['text'] . '}';
			$css .= 'nav .navLevel1 a.active {border-radius:' . $this->getData(['theme', 'menu', 'radius']) . '}'; 
			$css .= '#menu{text-align:' . $this->getData(['theme', 'menu', 'textAlign']) . '}';
			if($this->getData(['theme', 'menu', 'margin'])) {
				if(
					$this->getData(['theme', 'menu', 'position']) === 'site-first'
					OR $this->getData(['theme', 'menu', 'position']) === 'site-second'
				) {
					$css .= 'nav{padding:10px 10px 0 10px;}';
				}
				else {
					$css .= 'nav{padding:0 10px}';
				}				
			} else {
				$css .= 'nav{margin:0}';
			}
			if(
				$this->getData(['theme', 'menu', 'position']) === 'top'
				) {
					$css .= 'nav{padding:0 10px;}';
			}

			$css .= '#toggle span,#menu a{padding:' . $this->getData(['theme', 'menu', 'height']) .';font-family:"' . str_replace('+', ' ', $this->getData(['theme', 'menu', 'font'])) . '",sans-serif;font-weight:' . $this->getData(['theme', 'menu', 'fontWeight']) . ';font-size:' . $this->getData(['theme', 'menu', 'fontSize']) . ';text-transform:' . $this->getData(['theme', 'menu', 'textTransform']) . '}';			
			// Pied de page
			$colors = helper::colorVariants($this->getData(['theme', 'footer', 'backgroundColor']));
			if($this->getData(['theme', 'footer', 'margin'])) {
				$css .= 'footer{padding:0 20px;}';
			} else {
				$css .= 'footer{padding:0}';
			}
			$css .= 'footer span, #footerText > p {color:' . $this->getData(['theme', 'footer', 'textColor']) . ';font-family:"' . str_replace('+', ' ', $this->getData(['theme', 'footer', 'font'])) . '",sans-serif;font-weight:' . $this->getData(['theme', 'footer', 'fontWeight']) . ';font-size:' . $this->getData(['theme', 'footer', 'fontSize']) . ';text-transform:' . $this->getData(['theme', 'footer', 'textTransform']) . '}';
			$css .= 'footer{background-color:' . $colors['normal'] . ';color:' . $this->getData(['theme', 'footer', 'textColor']) . '}';
			$css .= 'footer a{color:' . $this->getData(['theme', 'footer', 'textColor']) . '}';
			$css .= 'footer #footersite > div {margin:' . $this->getData(['theme', 'footer', 'height']) . ' 0}';
			$css .= 'footer #footerbody > div {margin:' . $this->getData(['theme', 'footer', 'height']) . ' 0}';
			$css .= '#footerSocials{text-align:' . $this->getData(['theme', 'footer', 'socialsAlign']) . '}';
			$css .= '#footerText > p {text-align:' . $this->getData(['theme', 'footer', 'textAlign']) . '}';
			$css .= '#footerCopyright{text-align:' . $this->getData(['theme', 'footer', 'copyrightAlign']) . '}';
			// Marge supplémentaire lorsque le pied de page est fixe 
			if ( $this->getData(['theme', 'footer', 'fixed']) === true &&
				 $this->getData(['theme', 'footer', 'position']) === 'body') {
				$css .= "@media (min-width: 769px) { #site {margin-bottom: 100px;} }";
				$css .= "@media (max-width: 768px) { #site {margin-bottom: 150px;} }";
			}
			// Enregistre la personnalisation
			file_put_contents(self::DATA_DIR.'theme.css', $css);
			// Effacer le cache pour tenir compte de la couleur de fond TinyMCE
			header("Expires: Tue, 01 Jan 2000 00:00:00 GMT");
			header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
			header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
			header("Cache-Control: post-check=0, pre-check=0", false);
			header("Pragma: no-cache");
		}
		// Check la version rafraichissement du theme admin
		$cssVersion = preg_split('/\*+/', file_get_contents(self::DATA_DIR.'admin.css'));
		if(empty($cssVersion[1]) OR $cssVersion[1] !== md5(json_encode($this->getData(['admin'])))) {
			// Version
			$css = '/*' . md5(json_encode($this->getData(['admin']))) . '*/';
			$css .= '#site{background-color:' . $this->getData(['admin','backgroundColor']) . ';}';
			$css .= 'body, .row > div {font:' . $this->getData(['admin','fontSize']) . ' "' . $this->getData(['admin','fontText'])  . '", sans-serif;}';
			$css .= 'body h1, h2, h3, h4, h5, h6 {font-family:' .   $this->getData(['admin','fontTitle' ]) . ', sans-serif;color:' . $this->getData(['admin','colorTitle' ]) . ';}';
			$css .= 'body:not(.editorWysiwyg),.block h4,input[type=email],input[type=text],input[type=password],.inputFile,select,textarea:not(.editorWysiwyg),.inputFile,span .zwiico-help,.button.buttonGrey {color:' . $this->getData(['admin','colorText']) . ';}';
			$colors = helper::colorVariants($this->getData(['admin','backgroundColorButton']));
			$css .= '.button,input[type="checkbox"]:checked + label::before,.speechBubble{ background-color:' . $colors['normal'] . ';color:' . $this->getData(['admin','colorButtonText']) . ';}';
			$css .= '.speechBubble::before {border-color:' . $colors['normal'] . ' transparent transparent transparent;}';
			$css .= '.button:hover, button[type=submit]:hover { background-color:' . $colors['darken'] . ';}';
			$colors = helper::colorVariants($this->getData(['admin','backgroundColorButtonGrey']));
			$css .= '.button.buttonGrey {background: ' . $colors['normal'] . ';}.button.buttonGrey:hover {background:' . $colors['darken'] . '}.button.buttonGrey:active {background:' . $colors['veryDarken'] . '}';
			$colors = helper::colorVariants($this->getData(['admin','backgroundColorButtonRed']));
			$css .= '.button.buttonRed {background: ' . $colors['normal'] . ';}.button.buttonRed:hover {background:' . $colors['darken'] . '}.button.buttonRed:active {background:' . $colors['veryDarken'] . '}';			
			$colors = helper::colorVariants($this->getData(['admin','backgroundColorButtonGreen']));
			$css .= 'button[type=submit] {background-color: ' . $colors['normal']. ';color: ' . $this->getData(['admin','colorButtonText']) . '}button[type=submit]:hover {background-color: ' . $colors['darken'] . ';}';
			$colors = helper::colorVariants($this->getData(['admin','backgroundBlockColor']));
			$css .= '.block {border: 1px solid ' . $this->getData(['admin','borderBlockColor']) . ';}.block h4 {background: ' . $colors['normal'] . ';color:' . $colors['text']. ';}';
			// Enregistre la personnalisation
			file_put_contents(self::DATA_DIR.'admin.css', $css);
		}
	}
	/**
	 * Auto-chargement des classes
	 * @param string $className Nom de la classe à charger
	 */
	public static function autoload($className) {

		$classPath = strtolower($className) . '/' . strtolower($className) . '.php';
		// Module du coeur
		if(is_readable('core/module/' . $classPath)) {
			require 'core/module/' . $classPath;
		}
		// Module
		elseif(is_readable('module/' . $classPath)) {
			require 'module/' . $classPath;
		}
		// Librairie
		elseif(is_readable('core/vendor/' . $classPath)) {
			require 'core/vendor/' . $classPath;
		}
	}

	/**
	 * Routage des modules
	 */
	public function router() {
		// Installation
		if(
			$this->getData(['user']) === []
			AND $this->getUrl(0) !== 'install'
		) {
			http_response_code(302);
			header('Location:' . helper::baseUrl() . 'install');
			exit();
		}
		// Force la déconnexion des membres bannis
		if (
			$this->getUser('password') === $this->getInput('ZWII_USER_PASSWORD')
			AND $this->getUser('group') === self::GROUP_BANNED
		) {
			$user = new user;
			$user->logout();
		}
		// Mode maintenance
		if(
			$this->getData(['config', 'maintenance'])
			AND in_array($this->getUrl(0), ['maintenance', 'user']) === false
			AND $this->getUrl(1) !== 'login'
			AND (
				$this->getUser('password') !== $this->getInput('ZWII_USER_PASSWORD')
				OR (
					$this->getUser('password') === $this->getInput('ZWII_USER_PASSWORD')
					AND $this->getUser('group') < self::GROUP_ADMIN
				)
			)
		) {
			// Déconnexion
			$user = new user;
			$user->logout();
			// Rédirection
			http_response_code(302);
			header('Location:' . helper::baseUrl() . 'maintenance');
			exit();
		}
		// Check l'accès à la page
		$access = null;
		if($this->getData(['page', $this->getUrl(0)]) !== null) {
			if(
				$this->getData(['page', $this->getUrl(0), 'group']) === self::GROUP_VISITOR
				OR (
					$this->getUser('password') === $this->getInput('ZWII_USER_PASSWORD')
					AND $this->getUser('group') >= $this->getData(['page', $this->getUrl(0), 'group'])
				)
			) {
				$access = true;
			}
			else {
				if($this->getUrl(0) === $this->getData(['config', 'homePageId'])) {
					$access = 'login';
				}
				else {
					$access = false;
				}
			}
		}

		// Breadcrumb
		$title = $this->getData(['page', $this->getUrl(0), 'title']);
		if (!empty($this->getData(['page', $this->getUrl(0), 'parentPageId'])) &&
				$this->getData(['page', $this->getUrl(0), 'breadCrumb'])) {
				$title = '<a href="' . helper::baseUrl() . 
						$this->getData(['page', $this->getUrl(0), 'parentPageId']) .
						'">' .
						ucfirst($this->getData(['page',$this->getData(['page', $this->getUrl(0), 'parentPageId']), 'title'])) .
						'</a> &#8250; '.
						$this->getData(['page', $this->getUrl(0), 'title']);			
		} 
		
		// Importe la page
		if(
			$this->getData(['page', $this->getUrl(0)]) !== null
			AND $this->getData(['page', $this->getUrl(0), 'moduleId']) === ''
			AND $access
		) {
			$this->addOutput([
				'title' => $title,
				'content' => $this->getData(['page', $this->getUrl(0), 'content']),
				'metaDescription' => $this->getData(['page', $this->getUrl(0), 'metaDescription']),
				'metaTitle' => $this->getData(['page', $this->getUrl(0), 'metaTitle']),
				'typeMenu' => $this->getData(['page', $this->getUrl(0), 'typeMenu']),
				'iconUrl' => $this->getData(['page', $this->getUrl(0), 'iconUrl']),
				'disable' => $this->getData(['page', $this->getUrl(0), 'disable']),
				'contentRight' => $this->getData(['page',$this->getData(['page',$this->getUrl(0),'barRight']),'content']),
				'contentLeft'  => $this->getData(['page',$this->getData(['page',$this->getUrl(0),'barLeft']),'content']),
			]);
		}
		// Importe le module
		else {
			// Id du module, et valeurs en sortie de la page si il s'agit d'un module de page			

			if($access AND $this->getData(['page', $this->getUrl(0), 'moduleId'])) {
				$moduleId = $this->getData(['page', $this->getUrl(0), 'moduleId']);
				$this->addOutput([
					'title' => $title,
					// Meta description = 160 premiers caractères de l'article
					'metaDescription' => $this->getData(['page',$this->getUrl(0),'moduleId']) === 'blog' && !empty($this->getUrl(1))  
										? strip_tags(substr($this->getData(['module',$this->getUrl(0),$this->getUrl(1),'content']) ,0,159))
										: $this->getData(['page', $this->getUrl(0), 'metaDescription']),
					'metaTitle' => $this->getData(['page', $this->getUrl(0), 'metaTitle']),
					'typeMenu' => $this->getData(['page', $this->getUrl(0), 'typeMenu']),
					'iconUrl' => $this->getData(['page', $this->getUrl(0), 'iconUrl']),
					'disable' => $this->getData(['page', $this->getUrl(0), 'disable']),
					'contentRight' => $this->getData(['page',$this->getData(['page',$this->getUrl(0),'barRight']),'content']),
					'contentLeft'  => $this->getData(['page',$this->getData(['page',$this->getUrl(0),'barLeft']),'content'])
				]);
				$pageContent = $this->getData(['page', $this->getUrl(0), 'content']);
			}
			else {
				$moduleId = $this->getUrl(0);
				$pageContent = '';
			}
			// Check l'existence du module
			if(class_exists($moduleId)) {
				/** @var common $module */
				$module = new $moduleId;
				// Check l'existence de l'action
				$action = '';
				$ignore = true;
				foreach(explode('-', $this->getUrl(1)) as $actionPart) {
					if($ignore) {
						$action .= $actionPart;
						$ignore = false;
					}
					else {
						$action .= ucfirst($actionPart);
					}
				}
				$action = array_key_exists($action, $module::$actions) ? $action : 'index';
				if(array_key_exists($action, $module::$actions)) {
					$module->$action();
					$output = $module->output;
					// Check le groupe de l'utilisateur
					if(
						(
							$module::$actions[$action] === self::GROUP_VISITOR
							OR (
								$this->getUser('password') === $this->getInput('ZWII_USER_PASSWORD')
								AND $this->getUser('group') >= $module::$actions[$action]
							)
						)
						AND $output['access'] === true
					) {
						// Enregistrement du contenu de la méthode POST lorsqu'une notice est présente
						if(common::$inputNotices) {
							foreach($_POST as $postId => $postValue) {
								if(is_array($postValue)) {
									foreach($postValue as $subPostId => $subPostValue) {
										self::$inputBefore[$postId . '_' . $subPostId] = $subPostValue;
									}
								}
								else {
									self::$inputBefore[$postId] = $postValue;
								}
							}
						}
						// Sinon traitement des données de sortie qui requiert qu'aucune notice ne soit présente
						else {
							// Notification
							if($output['notification']) {
								if($output['state'] === true) {
									$notification = 'ZWII_NOTIFICATION_SUCCESS';
								}
								elseif($output['state'] === false) {
									$notification = 'ZWII_NOTIFICATION_ERROR';
								}
								else {
									$notification = 'ZWII_NOTIFICATION_OTHER';
								}
								$_SESSION[$notification] = $output['notification'];
							}
							// Redirection
							if($output['redirect']) {
								http_response_code(301);
								header('Location:' . $output['redirect']);
								exit();								
							}
						}						
						// Données en sortie applicables même lorsqu'une notice est présente
						// Affichage
						if($output['display']) { 
							$this->addOutput([
								'display' => $output['display']
							]);			
						}
						// Contenu brut
						if($output['content']) {
							$this->addOutput([
								'content' => $output['content']
							]);
						}
						// Contenu par vue
						elseif($output['view']) {
							// Chemin en fonction d'un module du coeur ou d'un module
							$modulePath = in_array($moduleId, self::$coreModuleIds) ? 'core/' : '';
							// CSS
							$stylePath = $modulePath . 'module/' . $moduleId . '/view/' . $output['view'] . '/' . $output['view'] . '.css';
							if(file_exists($stylePath)) {
								$this->addOutput([
									'style' => file_get_contents($stylePath)
								]);
							}
							// JS
							$scriptPath = $modulePath . 'module/' . $moduleId . '/view/' . $output['view'] . '/' . $output['view'] . '.js.php';
							if(file_exists($scriptPath)) {
								ob_start();
								include $scriptPath;
								$this->addOutput([
									'script' => ob_get_clean()
								]);
							}
							// Vue
							$viewPath = $modulePath . 'module/' . $moduleId . '/view/' . $output['view'] . '/' . $output['view'] . '.php';
							if(file_exists($viewPath)) {
								ob_start();
								include $viewPath;
								$modpos = $this->getData(['page', $this->getUrl(0), 'modulePosition']);
								if ($modpos === 'top') {
									$this->addOutput([
									'content' => ob_get_clean() . ($output['showPageContent'] ? $pageContent : '')]);
								}
								else if ($modpos === 'free') {
									if ( strstr($pageContent, '[MODULE]', true) === false)  {
										$begin = strstr($pageContent, '[]', true); } else {
										$begin = strstr($pageContent, '[MODULE]', true);
									}
									if (  strstr($pageContent, '[MODULE]') === false ) {
										$end = strstr($pageContent, '[]');} else {
										$end = strstr($pageContent, '[MODULE]');
										}
									$cut=8;
									$end=substr($end,-strlen($end)+$cut);
									$this->addOutput([
									'content' => ($output['showPageContent'] ? $begin : '') . ob_get_clean() . ($output['showPageContent'] ? $end : '')]);								}
								else {
									$this->addOutput([
									'content' => ($output['showPageContent'] ? $pageContent : '') . ob_get_clean()]);
								}
							}
						}
						// Librairies
						if($output['vendor'] !== $this->output['vendor']) {
							$this->addOutput([
								'vendor' => array_merge($this->output['vendor'], $output['vendor'])
							]);
						}
						if($output['title'] !== null) {										
							$this->addOutput([
								'title' => $output['title']
							]);
						}
						// Affiche le bouton d'édition de la page dans la barre de membre
						if($output['showBarEditButton']) {
							$this->addOutput([
								'showBarEditButton' => $output['showBarEditButton']
							]);
						}
					}
					// Erreur 403
					else {
						$access = false;
					}
				}
			}
		}
		// Erreurs
		if($access === 'login') {
			http_response_code(302);
			header('Location:' . helper::baseUrl() . 'user/login/');
			exit();
		}
		if($access === false) {
			http_response_code(403);
			$this->addOutput([
				'title' => 'Erreur 403',
				'content' => template::speech('Vous n\'êtes pas autorisé à accéder à cette page...')
			]);
		}
		elseif($this->output['content'] === '') {
			http_response_code(404);
			$this->addOutput([
				'title' => 'Erreur 404',
				'content' => template::speech('Oups ! La page demandée est introuvable...')
			]);
		}
		// Mise en forme des métas
		if($this->output['metaTitle'] === '') {
			if($this->output['title']) {
				$this->addOutput([
					'metaTitle' => strip_tags($this->output['title']) . ' - ' . $this->getData(['config', 'title'])
				]);
			}
			else {
				$this->addOutput([
					'metaTitle' => $this->getData(['config', 'title'])
				]);
			}
		}
		if($this->output['metaDescription'] === '') {
			$this->addOutput([
				'metaDescription' => $this->getData(['config', 'metaDescription'])
			]);
		}
				
	switch($this->output['display']) {
		// Layout vide
		case self::DISPLAY_LAYOUT_BLANK:
			require 'core/layout/blank.php';
			break;
		// Affichage en JSON
		case self::DISPLAY_JSON:
			header('Content-Type: application/json');
			echo json_encode($this->output['content']);
			break;
		// Layout alléger
		case self::DISPLAY_LAYOUT_LIGHT:
			require 'core/layout/light.php';
			break;
		// Layout principal
		case self::DISPLAY_LAYOUT_MAIN:
			require 'core/layout/main.php';
			break;							
		// Layout brut
		case self::DISPLAY_RAW:
			echo $this->output['content'];
			break;				
		}
	}

}

class layout extends common {

	private $core;

	/**
	 * Constructeur du layout
	 */
	public function __construct(core $core) {
		parent::__construct();
		$this->core = $core;
	}

	/**
	 * Affiche le script Google Analytics
	 */
	public function showAnalytics() {
		if($code = $this->getData(['config', 'analyticsId'])) {
			echo '<!-- Global site tag (gtag.js) - Google Analytics -->
				<script async src="https://www.googletagmanager.com/gtag/js?id='. $code .'"></script>
				<script>
				  window.dataLayer = window.dataLayer || [];
				  function gtag(){dataLayer.push(arguments);}
				  gtag("js", new Date());
				  gtag("config","'. $code .'");
				</script>';
		}
	}

	/**
	 * Affiche le contenu
	 * @param Page par défaut 
	 */
	public function showContent() {
		if(
			$this->core->output['title']
			AND (
				$this->getData(['page', $this->getUrl(0)]) === null
				OR $this->getData(['page', $this->getUrl(0), 'hideTitle']) === false
			)
		) {
			echo '<h1 id="sectionTitle">' . $this->core->output['title'] . '</h1>';
		}
		echo $this->core->output['content'];

	}


	/**
	 * Affiche le contenu de la barre gauche
	 * 
	 */
	public function showBarContentLeft() {
		// Détermine si le menu est présent
		if ($this->getData(['page',$this->getData(['page',$this->getUrl(0),'barLeft']),'displayMenu']) === 'none') {
			// Pas de menu
			echo $this->core->output['contentLeft'];
		} else {
			// $mark contient 0 le menu est positionné à la fin du contenu
			$contentLeft = str_replace ('[]','[MENU]',$this->core->output['contentLeft']);
			$contentLeft = str_replace ('[menu]','[MENU]',$contentLeft);
			$mark = strrpos($contentLeft,'[MENU]')  !== false ? strrpos($contentLeft,'[MENU]') : strlen($contentLeft);		
			echo substr($contentLeft,0,$mark);			
			echo '<div id="menuSideLeft">';
			echo $this->showMenuSide($this->getData(['page',$this->getData(['page',$this->getUrl(0),'barLeft']),'displayMenu']) === 'parents' ? false : true);
			echo '</div>';
			echo substr($contentLeft,$mark+6,strlen($contentLeft));			
		}						
	}

	/**
	 * Affiche le contenu de la barre droite
	 */
	public function showBarContentRight() {
		// Détermine si le menu est présent
		if ($this->getData(['page',$this->getData(['page',$this->getUrl(0),'barRight']),'displayMenu']) === 'none') {
			// Pas de menu
			echo $this->core->output['contentRight'];
		} else {
			// $mark contient 0 le menu est positionné à la fin du contenu
			$contentRight = str_replace ('[]','[MENU]',$this->core->output['contentRight']);
			$contentRight = str_replace ('[menu]','[MENU]',$contentRight);
			$mark = strrpos($contentRight,'[MENU]')  !== false ? strrpos($contentRight,'[MENU]') : strlen($contentRight);		
			echo substr($contentRight,0,$mark);			
			echo '<div id="menuSideRight">';
			echo $this->showMenuSide($this->getData(['page',$this->getData(['page',$this->getUrl(0),'barRight']),'displayMenu']) === 'parents' ? false : true);
			echo '</div>';
			echo substr($contentRight,$mark+6,strlen($contentRight));			
		}	
	}

	/**
	 * Affiche le texte du footer
	 */
	public function showFooterText() {
		if($footerText = $this->getData(['theme', 'footer', 'text']) OR $this->getUrl(0) === 'theme') {
			echo '<div id="footerText">' . $footerText . '</div>';
		}
	}

     /**
     * Affiche le copyright
     */
    public function showCopyright() {
		// Ouverture Bloc copyright
		$items = '<div id="footerCopyright">';
		$items .= '<span id="footerFontCopyright">';
		// Affichage de motorisé par 
		$items .= '<span id="footerDisplayCopyright" ';
		$items .= $this->getData(['theme','footer','displayCopyright']) === false ? 'class="displayNone"' : '';
		$items .= '>Motorisé&nbsp;par&nbsp;</span>';
		// Toujours afficher le nom du CMS
		$items .= '<span id="footerZwiiCMS">';
		$items .= '<a href="http://zwiicms.com/" onclick="window.open(this.href);return false" data-tippy-content="Zwii CMS sans base de données, très léger et performant">ZwiiCMS</a>';		
		$items .= '</span>';
		// Affichage du numéro de version
		$items .= '<span id="footerDisplayVersion"';
		$items .= $this->getData(['theme','footer','displayVersion']) === false ? ' class="displayNone"' : '';
		$items .= '><wbr>&nbsp;'. common::ZWII_VERSION ;			
		$items .= '</span>';
		// Affichage du sitemap
		$items .= '<span id="footerDisplaySiteMap"';
		$items .= $this->getData(['theme','footer','displaySiteMap']) ===  false ? ' class="displayNone"' : '';
		$items .=  '><wbr>&nbsp;|&nbsp;<a href="' . helper::baseUrl() .  'sitemap" data-tippy-content="Plan du site" >Plan&nbsp;du&nbsp;site</a>';
		$items .= '</span>';
        // Affichage du module de recherche
 		$items .= '<span id="footerDisplaySearch"';
		$items .= $this->getData(['theme','footer','displaySearch']) ===  false ? ' class="displayNone"' : '';
		$items .=  '><wbr>&nbsp;|&nbsp;<a href="' . helper::baseUrl() .  'search" data-tippy-content="Rechercher dans le site" >Rechercher</a>';
		$items .= '</span>';
		// Affichage des mentions légales
		$items .= '<span id="footerDisplayLegal"';
		$items .= $this->getData(['theme','footer','displayLegal']) ===  false ? ' class="displayNone" >' : '>';
		if ($this->getData(['config','legalPageId']) !== '') {
			$items .=  '<wbr>&nbsp;|&nbsp;<a href="' . helper::baseUrl() . $this->getData(['config','legalPageId']) . '" data-tippy-content="Mentions Légales">Mentions légales</a>';
		}
		$items .= '</span>';		
		// Affichage du lien de connexion 
		if(
            (
                $this->getData(['theme', 'footer', 'loginLink'])
                AND $this->getUser('password') !== $this->getInput('ZWII_USER_PASSWORD')
            )
            OR $this->getUrl(0) === 'theme'
        ) {
			$items .= '<span id="footerLoginLink" ' . 
			($this->getUrl(0) === 'theme' ? 'class="displayNone"' : '') . 
			'><wbr>&nbsp;|&nbsp;<a href="' . helper::baseUrl() . 'user/login/' . 
			strip_tags(str_replace('/', '_', $this->getUrl())) . 
			'" data-tippy-content="Connexion à l\'administration" rel="nofollow">Connexion</a></span>';
		}
		// Fermeture du bloc copyright
        $items .= '</span></div>';
        echo $items;
	}
	
	
	/**
	 * Affiche les réseaux sociaux
	 */
	public function showSocials() {
		$socials = '';
		foreach($this->getData(['config', 'social']) as $socialName => $socialId) {
			switch($socialName) {
				case 'facebookId':
					$socialUrl = 'https://www.facebook.com/';
					$title = 'Facebook';
					break;
				case 'linkedinId':
					$socialUrl = 'https://fr.linkedin.com/in/';
					$title = 'Linkedin';
					break;
				case 'instagramId':
					$socialUrl = 'https://www.instagram.com/';
					$title = 'Instagram';
					break;
				case 'pinterestId':
					$socialUrl = 'https://pinterest.com/';
					$title = 'Pinterest';
					break;
				case 'twitterId':
					$socialUrl = 'https://twitter.com/';
					$title = 'Twitter';
					break;
				case 'youtubeId':
					$socialUrl = 'https://www.youtube.com/channel/';
					$title = 'Chaîne YouTube';
					break;
				case 'youtubeUserId':
					$socialUrl = 'https://www.youtube.com/user/';
					$title = 'YouTube';
					break;					
				case 'githubId':
					$socialUrl = 'https://www.github.com/';
					$title = 'Github';
					break;
				default:
					$socialUrl = '';
			}
			if($socialId !== '') {
				$socials .= '<a href="' . $socialUrl . $socialId . '" onclick="window.open(this.href);return false" data-tippy-content="' . $title . '">' . template::ico(substr(str_replace('User','',$socialName), 0, -2)) . '</a>';
			}
		}
		if($socials !== '') {
			echo '<div id="footerSocials">' . $socials . '</div>';
		}
	}



	/**
	 * Affiche le favicon
	 */
	public function showFavicon() {
		// Light scheme
		$favicon = $this->getData(['config', 'favicon']);		
		if($favicon &&
			file_exists(self::FILE_DIR.'source/' . $favicon)
			) {
			echo '<link rel="shortcut icon" media="(prefers-color-scheme:light)" href="' . helper::baseUrl(false) . self::FILE_DIR.'source/' . $favicon . '">';
		} else {
			echo '<link rel="shortcut icon" media="(prefers-color-scheme:light)"  href="' . helper::baseUrl(false) . 'core/vendor/zwiico/ico/favicon.ico">';
		}
		// Dark scheme
		$faviconDark = $this->getData(['config', 'faviconDark']);	
		if(!empty($faviconDark) &&
		file_exists(self::FILE_DIR.'source/' . $faviconDark)
		) {
			echo '<link rel="shortcut icon" media="(prefers-color-scheme:dark)" href="' . helper::baseUrl(false) . self::FILE_DIR.'source/' . $faviconDark . '">';
			echo '<script src="https://unpkg.com/favicon-switcher@1.2.0/dist/index.js" crossorigin="anonymous" type="application/javascript"></script>';
		}  	
	}


	/**
	 * Affiche le menu
	 */
	public function showMenu() {
		// Met en forme les items du menu
		$items = '';
		$currentPageId = $this->getData(['page', $this->getUrl(0)]) ? $this->getUrl(0) : $this->getUrl(2);
		foreach($this->getHierarchy() as $parentPageId => $childrenPageIds) {
			// Passer les entrées masquées	
			// Propriétés de l'item
			$active = ($parentPageId === $currentPageId OR in_array($currentPageId, $childrenPageIds)) ? ' class="active"' : '';
			$targetBlank = $this->getData(['page', $parentPageId, 'targetBlank']) ? ' target="_blank"' : '';
			// Mise en page de l'item
			$items .= '<li>';
			
			if ( $this->getData(['page',$parentPageId,'disable']) === true
				 AND $this->getUser('password') !== $this->getInput('ZWII_USER_PASSWORD')	)

					{$items .= '<a href="'.$this->getUrl(1).'">';
			} else {
					$items .= '<a href="' . helper::baseUrl() . $parentPageId . '"' . $active . $targetBlank . '>';	
			}

			switch ($this->getData(['page', $parentPageId, 'typeMenu'])) {
				case '' :
				    $items .= $this->getData(['page', $parentPageId, 'title']);
				    break;
				case 'text' :
				    $items .= $this->getData(['page', $parentPageId, 'title']);
				    break;
				case 'icon' :
				    if ($this->getData(['page', $parentPageId, 'iconUrl']) != "") {
				    $items .= '<img alt="'.$this->getData(['page', $parentPageId, 'title']).'" src="'. helper::baseUrl(false) .self::FILE_DIR.'source/'.$this->getData(['page', $parentPageId, 'iconUrl']).'" />';
				    } else {
				    $items .= $this->getData(['page', $parentPageId, 'title']);
				    }
				    break;
				case 'icontitle' :
				    if ($this->getData(['page', $parentPageId, 'iconUrl']) != "") {
				    	$items .= '<img alt="'.$this->getData(['page', $parentPageId, 'title']).'" src="'. helper::baseUrl(false) .self::FILE_DIR.'source/'.$this->getData(['page', $parentPageId, 'iconUrl']).'" data-tippy-content="';
				   	 	$items .= $this->getData(['page', $parentPageId, 'title']).'"/>';
				    } else {
				  	 	$items .= $this->getData(['page', $parentPageId, 'title']);
				    }
					break;
		       }
			// Cas où les pages enfants enfant sont toutes masquées dans le menu
			// ne pas afficher de symbole lorsqu'il n'y a rien à afficher
			$totalChild = 0;
			$disableChild = 0;
			foreach($childrenPageIds as $childKey) {
				$totalChild += 1;
			}	
			if($childrenPageIds && $disableChild !== $totalChild  &&
				$this->getdata(['page',$parentPageId,'hideMenuChildren']) === false) {
				$items .= template::ico('down', 'left');
			}
			// ------------------------------------------------	
			$items .= '</a>';
			if ($this->getdata(['page',$parentPageId,'hideMenuChildren']) === true ||
				empty($childrenPageIds)) {
				continue;
			}
			$items .= '<ul class="navLevel2">';
			foreach($childrenPageIds as $childKey) {			
				// Propriétés de l'item
				$active = ($childKey === $currentPageId) ? ' class="active"' : '';
				$targetBlank = $this->getData(['page', $childKey, 'targetBlank']) ? ' target="_blank"' : '';				
				// Mise en page du sous-item
				$items .= '<li>';
				if ( $this->getData(['page',$childKey,'disable']) === true
					AND $this->getUser('password') !== $this->getInput('ZWII_USER_PASSWORD')	) {
						$items .= '<a href="'.$this->getUrl(1).'">';
				} else {
					$items .= '<a href="' . helper::baseUrl() . $childKey . '"' . $active . $targetBlank  .  '>';			
				}

				switch ($this->getData(['page', $childKey, 'typeMenu'])) {
					case '' :
						$items .= $this->getData(['page', $childKey, 'title']);
						break;
					case 'text' :
						$items .= $this->getData(['page', $childKey, 'title']);
						break;
					case 'icon' :
						if ($this->getData(['page', $childKey, 'iconUrl']) != "") {
						$items .= '<img alt="'.$this->getData(['page', $parentPageId, 'title']).'" src="'. helper::baseUrl(false) .self::FILE_DIR.'source/'.$this->getData(['page', $childKey, 'iconUrl']).'" />';
						} else {
						$items .= $this->getData(['page', $parentPageId, 'title']);
						}
						break;
					case 'icontitle' :
						if ($this->getData(['page', $childKey, 'iconUrl']) != "") {
						$items .= '<img alt="'.$this->getData(['page', $parentPageId, 'title']).'" src="'. helper::baseUrl(false) .self::FILE_DIR.'source/'.$this->getData(['page', $childKey, 'iconUrl']).'" data-tippy-content="';
						$items .= $this->getData(['page', $childKey, 'title']).'"/>';
						} else {
						$items .= $this->getData(['page', $childKey, 'title']);
						}
						break;
					case 'icontext' :
						if ($this->getData(['page', $childKey, 'iconUrl']) != "") {
						$items .= '<img alt="'.$this->getData(['page', $parentPageId, 'title']).'" src="'. helper::baseUrl(false) .self::FILE_DIR.'source/'.$this->getData(['page', $childKey, 'iconUrl']).'" />';
						$items .= $this->getData(['page', $childKey, 'title']);
						} else {
						$items .= $this->getData(['page', $childKey, 'title']);
						}
						break;
				}
				$items .= '</a></li>';
			}
			$items .= '</ul>';
		}
		// Lien de connexion
		if(
			(
				$this->getData(['theme', 'menu', 'loginLink'])
				AND $this->getUser('password') !== $this->getInput('ZWII_USER_PASSWORD')
			)
			OR $this->getUrl(0) === 'theme'
		) {
			$items .= '<li id="menuLoginLink" ' . 
			($this->getUrl(0) === 'theme' ? 'class="displayNone"' : '') . 
			'><a href="' . helper::baseUrl() . 'user/login/' . 
			strip_tags(str_replace('/', '_', $this->getUrl())) . 
			'">Connexion</a></li>';
		}
		// Retourne les items du menu
		echo '<ul class="navLevel1">' . $items . '</ul>';
	}

	/**
	 * Générer un menu pour la barre latérale
	 * Uniquement texte 
	 * @param onlyChildren n'affiche les sous-pages de la page actuelle
	 */
	public function showMenuSide($onlyChildren = null) {
		// Met en forme les items du menu
		$items = '';
		// Nom de la page courante
		$currentPageId = $this->getData(['page', $this->getUrl(0)]) ? $this->getUrl(0) : $this->getUrl(2);
		// Nom de la page parente
		$currentParentPageId = $this->getData(['page',$currentPageId,'parentPageId']);
		// Détermine si on affiche uniquement le parent et les enfants
		// Filtre contient le nom de la page parente

		if ($onlyChildren === true) {
			if (empty($currentParentPageId)) { 
				$filterCurrentPageId = $currentPageId;
			} else {
				$filterCurrentPageId = $currentParentPageId;				
			}
		} else {
			$items .= '<ul class="menuSide">';
		}

		foreach($this->getHierarchy() as $parentPageId => $childrenPageIds) {
			// Ne pas afficher les entrées masquées
			if ($this->getData(['page',$parentPageId,'hideMenuSide']) === true ) {
				continue;
			}
			// Filtre actif et nom de la page parente courante différente, on sort de la boucle
			if ($onlyChildren === true && $parentPageId !== $filterCurrentPageId) {
				continue;
			}
			// Propriétés de l'item
			$active = ($parentPageId === $currentPageId OR in_array($currentPageId, $childrenPageIds)) ? ' class="active"' : '';
			$targetBlank = $this->getData(['page', $parentPageId, 'targetBlank']) ? ' target="_blank"' : '';
			// Mise en page de l'item;
			// Ne pas afficher le parent d'une sous-page quand l'option est sélectionnée.
			if ($onlyChildren === false) {
				$items .= '<li class="menuSideChild">';
				if ( $this->getData(['page',$parentPageId,'disable']) === true
					AND $this->getUser('password') !== $this->getInput('ZWII_USER_PASSWORD')	) {
						$items .= '<a href="'.$this->getUrl(1).'">';
				} else {
						$items .= '<a href="' . helper::baseUrl() . $parentPageId . '"' . $active . $targetBlank . '>';	
				}
				$items .= $this->getData(['page', $parentPageId, 'title']);
				$items .= '</a>';
			}
			$itemsChildren = '';
			foreach($childrenPageIds as $childKey) {
				// Passer les entrées masquées
				if ($this->getData(['page',$childKey,'hideMenuSide']) === true ) {
					continue;
				}
				
				// Propriétés de l'item
				$active = ($childKey === $currentPageId) ? ' class="active"' : '';
				$targetBlank = $this->getData(['page', $childKey, 'targetBlank']) ? ' target="_blank"' : '';				
				// Mise en page du sous-item
				$itemsChildren .= '<li class="menuSideChild">';

				if ( $this->getData(['page',$childKey,'disable']) === true
					AND $this->getUser('password') !== $this->getInput('ZWII_USER_PASSWORD')	) {
						$itemsChildren .= '<a href="'.$this->getUrl(1).'">';
				} else {
					$itemsChildren .= '<a href="' . helper::baseUrl() . $childKey . '"' . $active . $targetBlank . '>';
				}

				$itemsChildren .= $this->getData(['page', $childKey, 'title']);					
				$itemsChildren .= '</a></li>';
			}
			// Concaténe les items enfants
			if (!empty($itemsChildren)) {
				$items .= '<ul class="menuSideChild">';
				$items .= $itemsChildren;
				$items .= '</ul>';
			} else {
				$items .= '</li>';
			}

		}
		if ($onlyChildren === false) {
			$items .= '</ul>';
		}
		// Retourne les items du menu
		echo  $items;
	}



	/**
	 * Affiche le meta titre
	 */
	public function showMetaTitle() {
		echo '<title>' . $this->core->output['metaTitle'] . '</title>';
		echo '<meta property="og:title" content="' . $this->core->output['metaTitle'] . '" />';
		echo '<link rel="canonical" href="'. helper::baseUrl(true).$this->getUrl() .'" />';		
	}

	/**
	 * Affiche la meta description
	 */
	public function showMetaDescription() {
			echo '<meta name="description" content="' . $this->core->output['metaDescription'] . '" />';
			echo '<meta property="og:description" content="' . $this->core->output['metaDescription'] . '" />';
	}

	/**
	 * Affiche le meta type
	 */
	public function showMetaType() {
		echo '<meta property="og:type" content="website" />';
	}

	/**
	 * Affiche la meta image (site screenshot)
	 */
	public function showMetaImage() {
		echo '<meta property="og:image" content="' . helper::baseUrl() .self::FILE_DIR.'source/screenshot.png" />';
	}



	/**
	 * Affiche la notification
	 */
	public function showNotification() {
		if (common::$importNotices) {
			$notification = common::$importNotices [0];
			$notificationClass = 'notificationSuccess';
		}
		if(common::$inputNotices) {
			$notification = 'Impossible de soumettre le formulaire, car il contient des erreurs';
			$notificationClass = 'notificationError';
		}
		if (common::$coreNotices) {
			$notification = 'Données absentes, restauration de <p> | ';
			foreach (common::$coreNotices as $item) $notification .= $item . ' | ';
			$notificationClass = 'notificationError';
		}
		if(common::$inputNotices) {
			$notification = 'Impossible de soumettre le formulaire, car il contient des erreurs';
			$notificationClass = 'notificationError';
		}
		elseif(empty($_SESSION['ZWII_NOTIFICATION_SUCCESS']) === false) {
			$notification = $_SESSION['ZWII_NOTIFICATION_SUCCESS'];
			$notificationClass = 'notificationSuccess';
			unset($_SESSION['ZWII_NOTIFICATION_SUCCESS']);
		}
		elseif(empty($_SESSION['ZWII_NOTIFICATION_ERROR']) === false) {
			$notification = $_SESSION['ZWII_NOTIFICATION_ERROR'];
			$notificationClass = 'notificationError';
			unset($_SESSION['ZWII_NOTIFICATION_ERROR']);
		}
		elseif(empty($_SESSION['ZWII_NOTIFICATION_OTHER']) === false) {
			$notification = $_SESSION['ZWII_NOTIFICATION_OTHER'];
			$notificationClass = 'notificationOther';
			unset($_SESSION['ZWII_NOTIFICATION_OTHER']);
		}
		if(isset($notification) AND isset($notificationClass)) {
			echo '<div id="notification" class="' . $notificationClass . '">' . $notification . '<span id="notificationClose">' . template::ico('cancel') . '<!----></span><div id="notificationProgress"></div></div>';
		}
	}

	/**
	 * Affiche la barre de membre
	 */
	public function showBar() {
		if($this->getUser('password') === $this->getInput('ZWII_USER_PASSWORD')) {
			// Items de gauche
			$leftItems = '';
			if($this->getUser('group') >= self::GROUP_MODERATOR) {
				$leftItems .= '<li><select id="barSelectPage">';
				$leftItems .= '<option value="">Choisissez une page</option>';
				$leftItems .= '<optgroup label="Pages orphelines">';
				$orpheline = true ;
				$currentPageId = $this->getData(['page', $this->getUrl(0)]) ? $this->getUrl(0) : $this->getUrl(2);			
				foreach($this->getHierarchy(null,false) as $parentPageId => $childrenPageIds) {
					if ($this->getData(['page', $parentPageId, 'position']) !== 0  &&
						$orpheline ) {
							$orpheline = false;
							$leftItems .= '<optgroup label="Pages du menu">';
					} 
					// Exclure les barres
					if ($this->getData(['page', $parentPageId, 'block']) !== 'bar') {
						$leftItems .= '<option value="' . 
									helper::baseUrl() . 
									$parentPageId . '"' . 
									($parentPageId === $currentPageId ? ' selected' : false) . 
									($this->getData(['page', $parentPageId, 'disable']) === true ? ' class="inactive"' : '') .
									'>' . 
									$this->getData(['page', $parentPageId, 'title']) . 
									'</option>';
						foreach($childrenPageIds as $childKey) {
							$leftItems .= '<option value="' . 
											helper::baseUrl() . 
											$childKey . '"' . 
											($childKey === $currentPageId ? ' selected' : false) . 
											($this->getData(['page', $childKey, 'disable']) === true ? ' class="inactive"' : '') .
											'>&nbsp;&nbsp;&nbsp;&nbsp;' . 
											$this->getData(['page', $childKey, 'title']) . 
											'</option>';
						}
					}
				}
				$leftItems .= '</optgroup'>
				// Afficher les barres
				$leftItems .= '<optgroup label="Barres latérales">';
				foreach($this->getHierarchy(null, false,true) as $parentPageId => $childrenPageIds) {
					$leftItems .= '<option value="' . helper::baseUrl() . $parentPageId . '"' . ($parentPageId === $currentPageId ? ' selected' : false) . '>' . $this->getData(['page', $parentPageId, 'title']) . '</option>';
					foreach($childrenPageIds as $childKey) {
						$leftItems .= '<option value="' . helper::baseUrl() . $childKey . '"' . ($childKey === $currentPageId ? ' selected' : false) . '>&nbsp;&nbsp;&nbsp;&nbsp;' . $this->getData(['page', $childKey, 'title']) . '</option>';
					}
				}	
				$leftItems .= '</optgroup>';			
				$leftItems .= '</select></li>';
				$leftItems .= '<li><a href="' . helper::baseUrl() . 'page/add" data-tippy-content="Créer une page ou<br>une barre latérale">' . template::ico('plus') . '</a></li>';
				if(
					// Sur un module de page qui autorise le bouton de modification de la page
					$this->core->output['showBarEditButton']
					// Sur une page sans module
					OR $this->getData(['page', $this->getUrl(0), 'moduleId']) === ''
					// Sur une page d'accueil
					OR $this->getUrl(0) === ''
				) {
					$leftItems .= '<li><a href="' . helper::baseUrl() . 'page/edit/' . $this->getUrl(0) . '" data-tippy-content="Modifier la page">' . template::ico('pencil') . '</a></li>';
					if ($this->getData(['page', $this->getUrl(0),'moduleId'])) {
						$leftItems .= '<li><a href="' . helper::baseUrl() . $this->getUrl(0) . '/config' . '" data-tippy-content="Configurer le module">' . template::ico('gear') . '</a></li>';					
					}
					$leftItems .= '<li><a id="pageDelete" href="' . helper::baseUrl() . 'page/delete/' . $this->getUrl(0) . '&csrf=' . $_SESSION['csrf'] . '" data-tippy-content="Effacer la page">' . template::ico('trash') . '</a></li>';					
				}
			}
			// Items de droite
			$rightItems = '';
			if($this->getUser('group') >= self::GROUP_MODERATOR) {
				$rightItems .= '<li><a href="' . helper::baseUrl(false) . 'core/vendor/filemanager/dialog.php?type=0&akey=' . md5_file(self::DATA_DIR.'core.json') .'" data-tippy-content="Gérer les fichiers" data-lity>' . template::ico('folder') . '</a></li>';
			}
			if($this->getUser('group') >= self::GROUP_ADMIN) {
				$rightItems .= '<li><a href="' . helper::baseUrl() . 'user" data-tippy-content="Configurer les utilisateurs">' . template::ico('users') . '</a></li>';
				$rightItems .= '<li><a href="' . helper::baseUrl() . 'theme" data-tippy-content="Personnaliser le thème">' . template::ico('brush') . '</a></li>';
				$rightItems .= '<li><a href="' . helper::baseUrl() . 'config" data-tippy-content="Configurer le site">' . template::ico('cog-alt') . '</a></li>';
				// Mise à jour automatique
				// Une mise à jour est disponible + recherche auto activée + 1 jour de délais
				$lastAutoUpdate = mktime(0, 0, 0);		
				if( $this->getData(['config','autoUpdate']) === true &&
					$lastAutoUpdate > $this->getData(['core','lastAutoUpdate']) + 86400 && 
					helper::checkNewVersion(common::ZWII_UPDATE_CHANNEL)) {
						$this->setData(['core','updateAvailable', true]);
						$this->setData(['core','lastAutoUpdate',$lastAutoUpdate]);									
				}
				// Afficher le bouton : Mise à jour détectée + activée	
				if ( $this->getData(['core','updateAvailable']) === true &&
					$this->getData(['config','autoUpdate']) === true  ) {
					$rightItems .= '<li><a id="barUpdate" href="' . helper::baseUrl() . 'install/update" data-tippy-content="Mettre à jour Zwii '. common::ZWII_VERSION .' vers '. helper::getOnlineVersion(common::ZWII_UPDATE_CHANNEL) .'">' . template::ico('update colorRed') . '</a></li>';
				}
			}
			$rightItems .= '<li><a href="' . helper::baseUrl() . 'user/edit/' . $this->getUser('id'). '/' . $_SESSION['csrf'] . '" data-tippy-content="Configurer mon compte">' . template::ico('user', 'right') . '<span id="displayUsername">' .  $this->getUser('firstname') . ' ' . $this->getUser('lastname') . '</span></a></li>';
			$rightItems .= '<li><a id="barLogout" href="' . helper::baseUrl() . 'user/logout" data-tippy-content="Se déconnecter">' . template::ico('logout') . '</a></li>';
			// Barre de membre 
			echo '<div id="bar"><div class="container"><ul id="barLeft">' . $leftItems . '</ul><ul id="barRight">' . $rightItems . '</ul></div></div>';
		}
	}

	/**
	 * Affiche le script
	 */
	public function showScript() {
		ob_start();
		require 'core/core.js.php';
		$coreScript = ob_get_clean();
		echo '<script>' . helper::minifyJs($coreScript . $this->core->output['script']) . '</script>';
	}

	/**
	 * Affiche le style
	 */
	public function showStyle() {
		if($this->core->output['style']) {
			echo '<base href="' . helper::baseUrl(true) .'">';
			echo '<style type="text/css">' . helper::minifyCss($this->core->output['style']) . '</style>';
		}
	}

	/**
	 * Affiche l'import des librairies
	 */
	public function showVendor() {
		// Variables partagées
		$vars = 'var baseUrl = ' . json_encode(helper::baseUrl(false)) . ';';
		$vars .= 'var baseUrlQs = ' . json_encode(helper::baseUrl()) . ';';
		if(
			$this->getUser('password') === $this->getInput('ZWII_USER_PASSWORD')
			AND $this->getUser('group') >= self::GROUP_MODERATOR
		) {
			$vars .= 'var privateKey = ' . json_encode(md5_file(self::DATA_DIR.'core.json')) . ';';
		}
		echo '<script>' . helper::minifyJs($vars) . '</script>';
		// Librairies
		$moduleId = $this->getData(['page', $this->getUrl(0), 'moduleId']);
		foreach($this->core->output['vendor'] as $vendorName) {
			// Coeur
			if(file_exists('core/vendor/' . $vendorName . '/inc.json')) {
				$vendorPath = 'core/vendor/' . $vendorName . '/';
			}
			// Module
			elseif(
				$moduleId
				AND in_array($moduleId, self::$coreModuleIds) === false
				AND file_exists('module/' . $moduleId . '/vendor/' . $vendorName . '/inc.json')
			) {
				$vendorPath = 'module/' . $moduleId . '/vendor/' . $vendorName . '/';
			}
			// Sinon continue
			else {
				continue;
			}
			// Détermine le type d'import en fonction de l'extension de la librairie
			$vendorFiles = json_decode(file_get_contents($vendorPath . 'inc.json'));
			foreach($vendorFiles as $vendorFile) {
				switch(pathinfo($vendorFile, PATHINFO_EXTENSION)) {
					case 'css':
						echo '<link rel="stylesheet" href="' . helper::baseUrl(false) . $vendorPath . $vendorFile . '">';
						break;
					case 'js':
						echo '<script src="' . helper::baseUrl(false) . $vendorPath . $vendorFile . '"></script>';
						break;
				}
			}
		}
	}

}