<?php

namespace voilab\tctable;

/**
 * Permet de dessiner facilement des tables avec une gestion poussée des sauts
 * de page et des hauteurs de lignes, ainsi que l'intégration de plugins.
 *
 * Cette classe est ~15% plus lente qu'une création à la main des lignes et des
 * cellules. A noter que la génération d'un tel PDF (15% plus vite, donc) ne
 * tient pas compte efficacement des sauts de page. On peut donc estimer à
 * ~10% seulement la perte de vitesse avec ce système.
 *
 * Le principal noeud se situe dans {@link getCurrentRowHeight()} qui fait
 * appel à la méthode {@link \TCPDF::getNumLines()}, assez gourmande. Le second
 * noeud évidemment inévitable est {@link addCell()} qui dessine les cellules.
 * Tout le reste n'est que set/get de propriétés et petits foreach.
 *
 * Niveau optimisation, {@link \TCPDF::getNumLines()} est appelé 1x pour chaque
 * cellule multiline de chaque row, mais pas pour les headers. Autrement, on
 * boucle sur les colonnes définies:
 * <ul>
 *     <li>1x à chaque affichage de la ligne de headers</li>
 *     <li>2x à chaque affichage des lignes (1x pour trouver la hauteur max et
 *     1x pour les afficher)</li>
 *     <li>1x pour déterminer la largeur de la colonne FitColumn (plugin)</li>
 *     <li>1x par ligne pour déterminer la couleur de fond avec StripeRows
 *     (plugin)</li>
 * </ul>
 */
class TcTable {

    /**
     * Event: avant qu'une row de données ne soit ajoutée
     * <ul>
     *     <li><i>TcTable</i> <b>$table</b> la TcTable à l'origine de
     *     l'event</li>
     *     <li><i>array</i> <b>$data</b> les datas pour chaque colonne de cette
     *     ligne</li>
     *     <li><i>int</i> <b>$rowIndex</b> l'index de cette ligne</li>
     * </ul>
     * @return void|bool Retourner FALSE pour stopper la chaîne des events et
     * ne pas dessiner la ligne
     */
    const EV_ROW_ADD = 1;

    /**
     * Event: après qu'une row de données soit ajoutée
     * <ul>
     *     <li><i>TcTable</i> <b>$table</b> la TcTable à l'origine de
     *     l'event</li>
     *     <li><i>array</i> <b>$data</b> les datas pour chaque colonne de cette
     *     ligne</li>
     *     <li><i>int</i> <b>$rowIndex</b> l'index de cette ligne</li>
     * </ul>
     * @return void|bool Retourner FALSE pour stopper la chaîne des events
     */
    const EV_ROW_ADDED = 2;

    /**
     * Event: avant que ne soit calculé la hauteur de la row de données
     * <ul>
     *     <li><i>TcTable</i> <b>$table</b> la TcTable à l'origine de
     *     l'event</li>
     *     <li><i>string</i> <b>$column</b> l'index de la colonne en cours de
     *     calculation de hauteur</li>
     *     <li><i>array</i> <b>$data</b> les datas pour cette colonne</li>
     *     <li><i>array</i> <b>$columns</b> les datas pour chaque colonne de
     *     cette ligne</li>
     * </ul>
     * @return mixed la donnée retravaillée qui permettra de calculer la hauteur
     * de la cellule. Brise la chaîne des events si non null.
     */
    const EV_ROW_HEIGHT_GET = 3;

    /**
     * Event: avant que les headers ne soient ajoutés
     * <ul>
     *     <li><i>TcTable</i> <b>$table</b> la TcTable à l'origine de
     *     l'event</li>
     * </ul>
     * @return void|bool Retourner FALSE pour stopper la chaîne des events et
     * ne pas dessiner les headers
     */
    const EV_HEADER_ADD = 4;

    /**
     * Event: après que les headers soient ajoutés
     * <ul>
     *     <li><i>TcTable</i> <b>$table</b> la TcTable à l'origine de
     *     l'event</li>
     * </ul>
     * @return void|bool Retourner FALSE pour stopper la chaîne des events
     */
    const EV_HEADER_ADDED = 5;

    /**
     * Event: après qu'une colonne soit ajoutée à la définition de la table
     * <ul>
     *     <li><i>TcTable</i> <b>$table</b> la TcTable à l'origine de
     *     l'event</li>
     *     <li><i>string</i> <b>$column</b> l'index de cette colonne</li>
     *     <li><i>array</i> <b>$definition</b> la définition de cette
     *     colonne</li>
     * </ul>
     * @return void|bool Retourner FALSE pour stopper la chaîne des events
     */
    const EV_COLUMN_ADDED = 6;

    /**
     * Event: avant qu'une cellule ne soit ajoutée
     * <ul>
     *     <li><i>TcTable</i> <b>$table</b> la TcTable à l'origine de
     *     l'event</li>
     *     <li><i>string</i> <b>$column</b> l'index de cette colonne</li>
     *     <li><i>mixed</i> <b>$data</b> la donnée à afficher dans la
     *     cellule</li>
     *     <li><i>array</i> <b>$definition</b> la définition de cette colonne
     *     pour la ligne courante
     *     (ce n'est pas sa définition par défaut)</li>
     *     <li><i>array</i> <b>$columns</b> les datas pour chaque colonne de
     *     cette ligne</li>
     *     <li><i>bool</i> <b>$header</b> true si c'est une cellule header</li>
     * </ul>
     * @return mixed la donnée retravaillée à afficher dans la cellule. Brise
     * la chaîne des events si non null.
     */
    const EV_CELL_ADD = 7;

    /**
     * Event: après qu'une cellule soit ajoutée
     * <ul>
     *     <li><i>TcTable</i> <b>$table</b> la TcTable à l'origine de
     *     l'event</li>
     *     <li><i>string</i> <b>$column</b> l'index de cette colonne</li>
     *     <li><i>mixed</i> <b>$data</b> la donnée à afficher dans la
     *     cellule</li>
     *     <li><i>array</i> <b>$definition</b> la définition de cette colonne
     *     pour la ligne courante
     *     (ce n'est pas sa définition par défaut)</li>
     *     <li><i>array</i> <b>$columns</b> les datas pour chaque colonne de
     *     cette ligne</li>
     *     <li><i>bool</i> <b>$header</b> true si c'est une cellule header</li>
     * </ul>
     * @return void|bool Retourner FALSE pour stopper la chaîne des events
     */
    const EV_CELL_ADDED = 8;

    /**
     * Event: avant qu'un saut de page ne soit ajouté
     * <ul>
     *     <li><i>TcTable</i> <b>$table</b> la TcTable à l'origine de
     *     l'event</li>
     *     <li><i>array</i> <b>$columns</b> les datas pour chaque colonne de
     *     cette ligne</li>
     *     <li><i>int</i> <b>$rowIndex</b> l'index de cette ligne</li>
     *     <li><i>bool</i> <b>$widow</b> TRUE si on ajoute la page à cause des
     *     limitations des veuves, FALSE si c'est seulement la ligne courante
     *     qui dépasse de la marge du bas</li>
     * </ul>
     * @return void|false Retourner FALSE pour ne pas ajouter la page
     */
    const EV_PAGE_ADD = 9;

    /**
     * Event: après qu'un saut de page soit ajouté
     * <ul>
     *     <li><i>TcTable</i> <b>$table</b> la TcTable à l'origine de
     *     l'event</li>
     *     <li><i>array</i> <b>$columns</b> les datas pour chaque colonne de
     *     cette ligne</li>
     *     <li><i>int</i> <b>$rowIndex</b> l'index de cette ligne</li>
     *     <li><i>bool</i> <b>$widow</b> TRUE si on ajoute la page à cause des
     *     limitations des veuves, FALSE si c'est seulement la ligne courante
     *     qui dépasse de la marge du bas</li>
     * </ul>
     * @return void|bool Retourner FALSE pour stopper la chaîne des events et
     * pour stopper la chaîne des events
     */
    const EV_PAGE_ADDED = 10;

    /**
     * Event: avant que les lignes de la table ne soient ajoutées
     * <ul>
     *     <li><i>TcTable</i> <b>$table</b> la TcTable à l'origine de
     *     l'event</li>
     *     <li><i>array</i> <b>$rows</b> les datas complètes de la table</li>
     * </ul>
     * @return void|bool Retourner FALSE pour stopper la chaîne des events et
     * ne pas dessiner les lignes
     */
    const EV_BODY_ADD = 11;

    /**
     * Event: après que les lignes de la table aient été ajoutées
     * <ul>
     *     <li><i>TcTable</i> <b>$table</b> la TcTable à l'origine de
     *     l'event</li>
     *     <li><i>array</i> <b>$rows</b> les datas complètes de la table</li>
     * </ul>
     * @return void|bool Retourner FALSE pour stopper la chaîne des events
     */
    const EV_BODY_ADDED = 12;

    /**
     * Le pdf
     * @var \TCPDF
     */
    private $pdf;

    /**
     * Les définitions de colonnes communes à toute les colonnes
     * @var array
     */
    private $defaultColumnDefinition = [];

    /**
     * Définition des colonnes
     * @var array
     */
    private $columnDefinition = [];

    /**
     * Au moment de dessiner une ligne, on copie la columnDefinition dans cette
     * propriété pour pouvoir la modifier sans changer le défaut
     * @var array
     */
    private $rowDefinition = [];

    /**
     * Liste des événements à lancer
     * @var array
     */
    private $events = [];

    /**
     * Liste des plugins liés à cette table
     * @var Plugin[]
     */
    private $plugins = [];

    /**
     * Détermine si on affiche les headers dans le {@link addBody()}
     * @var bool
     */
    private $showHeader = true;

    /**
     * La hauteur minimale d'une colonne
     * @var float
     */
    private $columnHeight;

    /**
     * La hauteur du footer de la table
     * @var float
     */
    private $footerHeight = 0;

    /**
     * La hauteur calculée pour la ligne en cours de traitement
     * @var float
     */
    private $rowHeight;

    /**
     * Le nombre de lignes de table minimales à avoir sur une nouvelle page
     * @var int
     */
    private $minWidowsOnPage;

    /**
     * Au lancement du addBody, on calcule la hauteur des veuves pour prévoir
     * leur insertion sur la page courante (si non, on ajoute une nouvelle
     * page). Pour éviter de calculer leur hauteur une fois là, puis une 2e
     * fois lors du parse de la row, on enregistre les hauteurs ici pour les
     * réutiliser.
     * @var array
     */
    private $_widowsCalculatedHeight = [];

    /**
     * Constructeur. Reçoit un pdf en argument sur lequel va pouvoir s'opérer
     * toutes les méthodes de pdf. Pour que les sauts de page soient
     * cohérents, il FAUT définir {@link \TCPDF::SetAutoPageBreak()} avec une
     * margin bottom (par exemple 2cm).
     *
     * @param \TCPDF $pdf
     * @param float $minColumnHeight la hauteur minimale des lignes
     * @param int $minWidowsOnPage le nombre de ligne minimum qu'on veut voir
     * sur la dernière page. 0 = pas de check
     */
    public function __construct(\TCPDF $pdf, $minColumnHeight, $minWidowsOnPage) {
        $this->pdf = $pdf;
        $this->columnHeight = $minColumnHeight;
        $this->minWidowsOnPage = $minWidowsOnPage;
    }

    /**
     * Ajoute un plugin
     *
     * @param Plugin $plugin
     * @param string $key une clé pour retrouver le plugin avec getPlugin()
     * @return TcTable
     */
    public function addPlugin(Plugin $plugin, $key = null) {
        if ($key) {
            $this->plugins[$key] = $plugin;
        } else {
            $this->plugins[] = $plugin;
        }
        $plugin->configure($this);
        return $this;
    }

    /**
     * Récupère un plugin selon sa clé d'indexation
     *
     * @param mixed $key l'index (0, 1, 2, etc) ou une string
     * @return Plugin
     */
    public function getPlugin($key) {
        return isset($this->plugins[$key]) ? $this->plugins[$key] : null;
    }

    /**
     * Set une action particulière à faire sur un événement particulier
     *
     * @param string $event le nom de l'event
     * @param callable $fn la fonction à lancer
     * @return TcTable
     */
    public function on($event, callable $fn) {
        if (!isset($this->events[$event])) {
            $this->events[$event] = [];
        }
        $this->events[$event][] = $fn;
        return $this;
    }

    /**
     * Parcourt la liste des actions renseignées sur cet event et les lance
     * toutes
     *
     * @param string $event le nom de l'event
     * @param array $args des arguments particuliers à transmettre aux funcs
     * @param bool $acceptReturn TRUE pour qu'un retour non-null d'un plugin
     * soit immédiatement utilisé par TcTable
     * @return mixed l'éventuel contenu souhaité ou null
     */
    public function trigger($event, array $args = [], $acceptReturn = false) {
        array_unshift($args, $this);
        if (isset($this->events[$event])) {
            foreach ($this->events[$event] as $fn) {
                $data = call_user_func_array($fn, $args);
                if ($acceptReturn && $data !== null) {
                    return $data;
                } elseif ($data === false) {
                    return false;
                }
            }
        }
    }

    /**
     * Retourne le pdf qui intègre cette table
     *
     * @return \TCPDF
     */
    public function getPdf() {
        return $this->pdf;
    }

    /**
     * Définit une colonne. Le tableau de config répond à la structure \TCPDF
     * concernant les Cell, les MultiCell et les Image
     *
     * Les habituels pour cellules mono et multilines:
     * <ul>
     *     <li><i>callable</i> <b>renderer</b>: fonction de rendu des données.
     *     Reçoit (TcTable $table, $data, array $columns). Attention, la méthode
     *     de rendu est appelée 2x, l'une pour le calcul de la hauteur de ligne
     *     et l'autre pour l'affichage effectif dans la cellule.</li>
     *     <li><i>string</i> <b>header</b>: header de la colonne</li>
     *     <li><i>float</i> <b>width</b>: largeur de la colonne</li>
     *     <li><i>string</i> <b>border</b>: bordure de colonne (LTBR)</li>
     *     <li><i>string</i> <b>align</b>: alignement horizontal du texte
     *     (LCR)</li>
     *     <li><i>string</i> <b>valign</b>: alignement vertical du texte
     *     (TCB)</li>
     * </ul>
     *
     * Les habituels pour cellules multilines
     * <ul>
     *     <li><i>bool</i> <b>isMultiLine</b>: true pour indiquer une cellule multiline</li>
     *     <li><i>bool</i> <b>isHtml</b>: true pour dire que le contenu est du HTML</li>
     * </ul>
     *
     * Les habituels pour les cellules images (expérimental)
     * <ul>
     *     <li><i>bool</i> <b>isImage</b>: indique si cette cellule est une
     *     image</li>
     *     <li><i>string</i> <b>type</b>: JPEG ou PNG</li>
     *     <li><i>bool</i> <b>resize</b>: voir doc {@link \TCPDF::Image}</li>
     *     <li><i>int</i> <b>dpi</b>: voir doc {@link \TCPDF::Image}</li>
     *     <li><i>string</i> <b>palign</b>: voir doc {@link \TCPDF::Image}</li>
     *     <li><i>bool</i> <b>isMask</b>: voir doc {@link \TCPDF::Image}</li>
     *     <li><i>mixed</i> <b>imgMask</b>: voir doc {@link \TCPDF::Image}</li>
     *     <li><i>bool</i> <b>hidden</b>: voir doc {@link \TCPDF::Image}</li>
     *     <li><i>bool</i> <b>fitOnPage</b>: voir doc {@link \TCPDF::Image}</li>
     *     <li><i>bool</i> <b>alt</b>: voir doc {@link \TCPDF::Image}</li>
     *     <li><i>array</i> <b>altImgs</b>: voir doc {@link \TCPDF::Image}</li>
     * </ul>
     *
     * Toutes les autres options possibles:
     * <ul>
     *     <li><i>float</i> <b>height</b>: hauteur minimale de la cellule (par
     *     défaut {@link setRowHeight()}</li>
     *     <li><i>bool</i> <b>ln</b>: est géré par TcTable. Cette option est
     *     ignorée.</li>
     *     <li><i>bool</i> <b>fill</b>: voir doc {@link \TCPDF::Cell}</li>
     *     <li><i>string</i> <b>link</b>: voir doc {@link \TCPDF::Cell}</li>
     *     <li><i>int</i> <b>stretch</b>: voir doc {@link \TCPDF::Cell}</li>
     *     <li><i>bool</i> <b>ignoreHeight</b>: voir doc {@link \TCPDF::Cell}</li>
     *     <li><i>string</i> <b>calign</b>: voir doc {@link \TCPDF::Cell}</li>
     *     <li><i>mixed</i> <b>x</b>: voir doc {@link \TCPDF::MultiCell}</li>
     *     <li><i>mixed</i> <b>y</b>: voir doc {@link \TCPDF::MultiCell}</li>
     *     <li><i>bool</i> <b>reseth</b>: voir doc {@link \TCPDF::MultiCell}</li>
     *     <li><i>float</i> <b>maxh</b>: voir doc {@link \TCPDF::MultiCell}</li>
     *     <li><i>bool</i> <b>autoPadding</b>: voir doc {@link \TCPDF::MultiCell}</li>
     *     <li><i>bool</i> <b>fitcell</b>: voir doc {@link \TCPDF::MultiCell}</li>
     *     <li><i>string</i> <b>cellPadding</b>: voir doc {@link \TCPDF::getNumLines}</li>
     * </ul>
     *
     * @param string $column
     * @param array $definition
     * @return TcTable
     */
    public function addColumn($column, array $definition) {
        // on set la nouvelle colonne avec ses configs par défaut. A noter que
        // le [ln] est toujours mis à FALSE pour cette nouvelle colonne insérée.
        // Lorsqu'on affichera le addBody(), on mettra TRUE pour la dernière.
        $this->columnDefinition[$column] = array_merge([
            'isMultiLine' => false,
            'isImage' => false,
            'renderer' => null,
            'header' => '',
            // cell
            'width' => 10,
            'height' => $this->getColumnHeight(),
            'border' => 0,
            'ln' => false,
            'align' => 'L',
            'fill' => false,
            'link' => '',
            'stretch' => 0,
            'ignoreHeight' => false,
            'calign' => 'T',
            'valign' => 'M',
            // multiCell
            'x' => '',
            'y' => '',
            'reseth' => true,
            'isHtml' => false,
            'maxh' => 0,
            'autoPadding' => true,
            'fitcell' => false,
            // images
            'type' => '',
            'resize' => false,
            'dpi' => 300,
            'palign' => '',
            'isMask' => false,
            'imgMask' => false,
            'hidden' => false,
            'fitOnPage' => false,
            'alt' => false,
            'altImgs' => [],
            // getNumLines
            'cellPadding' => ''
        ], $this->defaultColumnDefinition, $definition);

        $this->trigger(self::EV_COLUMN_ADDED, [$column, $this->columnDefinition[$column]]);
        return $this;
    }

    /**
     * Ajoute plusieurs colonnes d'un coup
     *
     * @see addColumn
     * @param array $columns
     * @return \mangetasoupe\pdf\TcTable
     */
    public function setColumns(array $columns) {
        foreach ($columns as $key => $def) {
            $this->addColumn($key, $def);
        }
        return $this;
    }

    /**
     * Set une donnée pour une colonne
     *
     * @param string $column
     * @param string $definition
     * @param mixed $value
     * @return TcTable
     */
    public function setColumnDefinition($column, $definition, $value) {
        $this->columnDefinition[$column][$definition] = $value;
        return $this;
    }

    /**
     * Set les définitions de colonnes communes à toutes les colonnes
     *
     * @see setColumns
     * @param array $definition
     * @return TcTable
     */
    public function setDefaultColumnDefinition(array $definition) {
        $this->defaultColumnDefinition = $definition;
        return $this;
    }

    /**
     * Récupère la liste de toutes les colonnes
     *
     * @return array
     */
    public function getColumns() {
        return $this->columnDefinition;
    }

    /**
     * Récupère la largeur d'une colonne
     *
     * @param string $column
     * @return float
     */
    public function getColumnWidth($column) {
        return $this->getColumn($column)['width'];
    }

    /**
     * Récupère la définition d'une colonne
     *
     * @param string $column
     * @return array
     */
    public function getColumn($column) {
        return $this->columnDefinition[$column];
    }

    /**
     * Set la hauteur minimale pour une ligne
     *
     * @param float $height
     * @return TcTable
     */
    public function setColumnHeight($height) {
        $this->columnHeight = $height;
        return $this;
    }

    /**
     * Retourne la hauteur minimale settée pour chaque cellule, qui forment
     * une ligne de contenu
     *
     * @return float
     */
    public function getColumnHeight() {
        return $this->columnHeight;
    }

    /**
     * Set la hauteur du footer de la table. Est utilisé pour adapter les
     * veuves sur la dernière page, dans le cas où le footer devait se retrouver
     * tout seul
     *
     * @param float $height
     * @return TcTable
     */
    public function setFooterHeight($height) {
        $this->footerHeight = $height;
        return $this;
    }

    /**
     * Détermine si on affiche ou non les headers lors de l'appel à
     * {@link addBody()}.
     *
     * @param bool $show
     * @return TcTable
     */
    public function setShowHeader($show) {
        $this->showHeader = $show;
        return $this;
    }

    /**
     * Récupère la largeur depuis le début de la table jusqu'à une colonne
     * donnée. La largeur de la colonne donnée n'est pas prise en compte dans
     * le calcul, on s'arrête au départ de celle-ci.
     *
     * Exemple: $table->getColumnWidthUntil('D');
     * <pre>
     * | A | B | C | D | E |
     * |-> | ->| ->|   |   |
     * </pre>
     *
     * @param string $column
     * @return float
     */
    public function getColumnWidthUntil($column) {
        return $this->getColumnWidthBetween('', $column);
    }

    /**
     * Calcul la largeur entre 2 colonnes. Les largeurs des deux colonnes sont
     * incluses dans le calcul.
     *
     * Exemple: $table->getColumnWidthBetween('B', 'D');
     * <pre>
     * | A | B | C | D | E |
     * |   |-> | ->| ->|   |
     * </pre>
     *
     * Si la colonne A est vide, réagit comme {@link TcTable::getColumnWidthUntil()}.
     * Si la colonne B est vide, réagit comme {@link TcTable::getColumnWidthFrom()}.
     *
     * @param string $columnA La colonne de départ
     * @param string $columnB La colonne finale
     * @return float
     */
    public function getColumnWidthBetween($columnA, $columnB) {
        $width = 0;
        $check = false;
        foreach ($this->columnDefinition as $key => $def) {
            // on commence l'addition soit depuis le début, soit depuis la
            // colonne A
            if ($key == $columnA || !$columnA) {
                $check = true;
            }
            // on stoppe tout si on demande la width depuis le début jusqu'à
            // la colonne B
            if (!$columnA && $key == $columnB) {
                break;
            }
            if ($check) {
                $width += $def['width'];
            }
            if ($key == $columnB) {
                break;
            }
        }
        return $width;
    }

    /**
     * Récupère la largeur depuis une colonne donnée jusqu'à la fin de la table.
     * La largeur de la colonne donnée est prise en compte dans le calcul, on
     * commence au départ de celle-ci.
     *
     * Exemple: $table->getColumnWidthFrom('D');
     * <pre>
     * | A | B | C | D | E |
     * |   |   |   |-> | ->|
     * </pre>
     *
     * @param string $column
     * @return float
     */
    public function getColumnWidthFrom($column) {
        return $this->getColumnWidthBetween($column, '');
    }

    /**
     * Retourne la largeur complète de la table
     *
     * @return float
     */
    public function getWidth() {
        return $this->getColumnWidthBetween('', '');
    }

    /**
     * Set la hauteur minimale pour la ligne en cours de traitement
     *
     * @param float $height
     * @return TcTable
     */
    public function setRowHeight($height) {
        $this->rowHeight = $height;
        return $this;
    }

    /**
     * Retourne la hauteur minimale settée pour chaque cellule, qui forment
     * la ligne de contenu en cours de traitement
     *
     * @return float
     */
    public function getRowHeight() {
        return $this->rowHeight;
    }

    /**
     * Récupère la configuration custom pour le dessin de la ligne en cours
     *
     * @return array
     */
    public function getRowDefinition() {
        return $this->rowDefinition;
    }

    /**
     * Set la configuration custom pour le dessin de la ligne en cours, en
     * permettant de modifier juste un élément de configuration d'une seule
     * colonne.
     *
     * @param string $column
     * @param string $definition
     * @param mixed $value
     * @return TcTable
     */
    public function setRowDefinition($column, $definition, $value) {
        $this->rowDefinition[$column][$definition] = $value;
        return $this;
    }

    /**
     * Set la configuration custom pour le dessin de la ligne en cours
     *
     * @param array $definition
     * @return TcTable
     */
    public function setRows(array $definition) {
        $this->rowDefinition = $definition;
        return $this;
    }

    /**
     * Ajoute les headers de la table
     *
     * @return TcTable
     */
    public function addHeader() {
        $this->copyDefaultColumnDefinitions([]);
        if ($this->trigger(self::EV_HEADER_ADD) !== false) {
            foreach ($this->columnDefinition as $key => $def) {
                $this->addCell($key, $def['header'], $this->columnDefinition, true);
            }
            $this->trigger(self::EV_HEADER_ADDED);
        }
        return $this;
    }

    /**
     * Ajoute les lignes du tableau. On en fait une méthode interne à TcTable
     * pour pouvoir plus efficacement gérer les sauts de page. Le callable
     * passé en paramètre reçoit les arguments suivants:
     * <ul>
     *     <li><i>TcTable</i> <b>$table</b> la TcTable</li>
     *     <li><i>array</i> <b>$line</b> la ligne de données courante</li>
     * </ul>
     * <ul>
     *     <li>Return <i>array</i> les données formattées ou les clés sont
     *     celles définies dans les {@link TcTable::addColumn()}</li>
     * </ul>
     *
     * @param array $rows toutes les lignes de données
     * @param callable $fn la fonction de formattage des données
     * @return TcTable
     */
    public function addBody(array $rows, callable $fn = null) {
        // on met la dernière colonne à TRUE pour le retour de ligne
        end($this->columnDefinition);
        $this->columnDefinition[key($this->columnDefinition)]['ln'] = true;

        $auto_pb = $this->pdf->getAutoPageBreak();
        $margins = $this->pdf->getMargins();
        $this->pdf->SetAutoPageBreak(false, $margins['bottom']);
        if ($this->trigger(self::EV_BODY_ADD, [$rows]) === false) {
            return $this;
        }
        if ($this->showHeader) {
            $this->addHeader();
        }
        $page_break_trigger = $this->pdf->getPageHeight() - $this->pdf->getBreakMargin() - $this->footerHeight;
        $count = count($rows);
        // on calcule la hauteur que prendra techniquement l'ensemble minimal
        // des veuves sur la dernière page
        $h = $this->getCalculatedWidowsHeight($rows, $fn);
        foreach ($rows as $index => $row) {
            // si on arrive potentiellement au risque qu'on n'ait pas assez de
            // lignes veuves sur la prochaine page, on ajoute une nouvelle page
            // en avance.
            if ($index + $this->minWidowsOnPage >= $count && $this->pdf->GetY() + $h >= $page_break_trigger) {
                if ($this->trigger(self::EV_PAGE_ADD, [$rows, $index, true]) !== false) {
                    $this->pdf->AddPage();
                    $this->trigger(self::EV_PAGE_ADDED, [$rows, $index, true]);
                }
            }
            $this->addRow($fn ? $fn($this, $row) : $row, $index);
        }
        $this->trigger(self::EV_BODY_ADDED, [$rows]);
        $this->_widowsCalculatedHeight = [];
        $this->pdf->SetAutoPageBreak($auto_pb, $margins['bottom']);
        return $this;
    }

    /**
     * Ajoute une ligne. Attend des paires 'column' => 'data'
     *
     * @param array $row
     * @param int $index le row index
     * @return TcTable
     */
    private function addRow(array $row, $index = null) {
        $this->copyDefaultColumnDefinitions($row, $index);
        if ($this->trigger(self::EV_ROW_ADD, [$row, $index]) === false) {
            return $this;
        }
        $row_definition = $this->rowDefinition;

        $h = current($row_definition)['height'];
        $page_break_trigger = $this->pdf->getPageHeight() - $this->pdf->getBreakMargin();
        if ($this->pdf->GetY() + $h >= $page_break_trigger) {
            if ($this->trigger(self::EV_PAGE_ADD, [$row, $index, false]) !== false) {
                $this->pdf->AddPage();
                $this->trigger(self::EV_PAGE_ADDED, [$row, $index, false]);
            }
            // on reset la définition de la row à dessiner car si on a dessiné
            // les headers en début de page, la definition aura été écrasée
            $this->rowDefinition = $row_definition;
        }

        foreach ($this->columnDefinition as $key => $value) {
            $this->addCell($key, isset($row[$key]) ? $row[$key] : '', $row);
        }
        $this->trigger(self::EV_ROW_ADDED, [$row, $index]);
        return $this;
    }

    /**
     * Calcule la hauteur que prendra techniquement l'ensemble minimal des
     * veuves sur la dernière page. Utilisé afin de forcer un saut de page si
     * on arrive pas à toutes les câler dans la page en cours.
     *
     * @param array $rows toutes les lignes de données
     * @param callable $fn la fonction du addBody pour la mise en forme des
     * données
     * @return float
     */
    private function getCalculatedWidowsHeight($rows, callable $fn) {
        $count = count($rows);
        $limit = $count - $this->minWidowsOnPage;
        $h = 0;
        if ($count && $limit >= 0) {
            for ($i = $count - 1; $i >= $limit; $i--) {
                $this->_widowsCalculatedHeight[$i] = $this->getCurrentRowHeight($fn ? $fn($this, $rows[$i]) : $rows[$i]);
                $h += $this->_widowsCalculatedHeight[$i];
            }
        }
        return $h;
    }

    /**
     * Copie les définitions de colonne dans une autre propriété. Ça permet
     * de la modifier comme on veut, tout en gardant les données par défaut.
     * Ainsi, à la row suivante, on aura de nouveau la configuration "normale".
     *
     * Ça permet à certains plugins de modifier temporairement, pour le draw
     * d'une row seulement, des informations de colonne
     *
     * @param array $columns les données pour la row
     * @param int $rowIndex le row index
     * @return void
     */
    private function copyDefaultColumnDefinitions(array $columns = null, $rowIndex = null) {
        $this->rowDefinition = $this->columnDefinition;
        // si l'index de la row courante correspond à celui d'une veuve dont
        // on a déjà calculé la hauteur, on reprend cette valeur
        $h = $rowIndex !== null && isset($this->_widowsCalculatedHeight[$rowIndex])
            ? $this->_widowsCalculatedHeight[$rowIndex]
            : ($columns !== null ? $this->getCurrentRowHeight($columns) : $this->getColumnHeight());

        $this->setRowHeight($h);
    }

    /**
     * Parcourt 1x tous les contenus de la ligne pour déterminer quel est le
     * contenu qui prend le plus de place, pour adapter la hauteur de toutes
     * les cellules
     *
     * @param array $row
     * @return float
     */
    private function getCurrentRowHeight(array $row) {
        // définition de la hauteur maximale des cellules
        $h = $this->getColumnHeight();
        $this->setRowHeight($h);
        foreach ($this->columnDefinition as $key => $def) {
            if (!isset($row[$key]) || !$def['isMultiLine']) {
                continue;
            }
            $data = $row[$key];
            if (is_callable($def['renderer'])) {
                $data = $def['renderer']($this, $data, $row);
            }
            $plugin_data = $this->trigger(self::EV_ROW_HEIGHT_GET, [$key, $data, $row], true);
            if ($plugin_data !== null) {
                $data = $plugin_data;
            }
            // le getNumLines ne tient pas compte de l'html. Pour quand même
            // avoir une notion de ligne, on remplace les br par des \n
            $nb = $this->pdf->getNumLines(strip_tags(str_replace(['<br>', '<br/>'], "\n", $data)), $def['width'],
                $def['reseth'], $def['autoPadding'],
                $def['cellPadding'], $def['border']);

            $hd = $nb * $h;
            if ($hd > $this->getRowHeight()) {
                $this->setRowHeight($hd);
            }
        }
        return $this->getRowHeight();
    }

    /**
     * Dessine une cellule
     *
     * @param string $column l'index de la colonne
     * @param mixed $data les données à afficher dans la cellule
     * @param array $row toutes les données de la ligne
     * @param bool $header true si on dessine la ligne des headers
     * @return TcTable
     */
    private function addCell($column, $data, array $row, $header = false) {
        if (!isset($this->rowDefinition[$column])) {
            return;
        }
        $c = $this->rowDefinition[$column];
        if (!$header && is_callable($c['renderer'])) {
            $data = $c['renderer']($this, $data, $row);
        }
        $plugin_data = $this->trigger(self::EV_CELL_ADD, [$column, $data, $c, $row, $header], true);
        if ($plugin_data !== null) {
            $data = $plugin_data;
        }
        $h = $this->getRowHeight();
        if ($c['isMultiLine']) {
            $this->pdf->MultiCell($c['width'], $h, $data, $c['border'],
                $c['align'], $c['fill'], $c['ln'], $c['x'], $c['y'], $c['reseth'],
                $c['stretch'], $c['isHtml'], $c['autoPadding'], $c['maxh'],
                $c['valign'], $c['fitcell']);
        } elseif ($c['isImage']) {
            $this->pdf->Image($data, $this->pdf->GetX() + $c['x'],
                $this->pdf->GetY() + $c['y'], $c['width'], $h,
                $c['type'], $c['link'], $c['align'], $c['resize'], $c['dpi'],
                $c['palign'], $c['isMask'], $c['imgMask'], $c['border'],
                $c['fitcell'], $c['hidden'], $c['fitOnPage'], $c['alt'],
                $c['altImgs']);
            $this->pdf->SetX($this->GetX() + $c['width']);
        } else {
            $this->pdf->Cell($c['width'], $h, $data, $c['border'],
                $c['ln'], $c['align'], $c['fill'], $c['link'], $c['stretch'],
                $c['ignoreHeight'], $c['calign'], $c['valign']);
        }
        $this->trigger(self::EV_CELL_ADDED, [$column, $c, $data, $row, $header]);
        return $this;
    }

}
