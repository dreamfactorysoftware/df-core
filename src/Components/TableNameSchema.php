<?php
namespace DreamFactory\Core\Components;

/**
 * TableNameSchema is the base class for representing the metadata of a database table.
 *
 * It may be extended by different DBMS driver to provide DBMS-specific table metadata.
 *
 * TableNameSchema provides the following information about a table:
 * <ul>
 * <li>{@link name}</li>
 * <li>{@link rawName}</li>
 * </ul>
 *
 */
class TableNameSchema
{
    /**
     * @var string name of this table.
     */
    public $name;
    /**
     * @var string Optional alias for this table. This alias can be used in the API to access the table.
     */
    public $alias;
    /**
     * @var string Optional label for this table.
     */
    public $label;
    /**
     * @var string Optional plural form of the label for of this table.
     */
    public $plural;
    /**
     * @var string Optional public description of this table.
     */
    public $description;

    public function __construct($name)
    {
        $this->name = $name;
        $this->label = static::camelize($this->name, '_', true);
        $this->plural = static::pluralize($this->label);
    }

    public function mergeDbExtras($extras)
    {
        $this->alias = $extras['alias'];
        $this->description = $extras['description'];
        if (!empty($extras['label'])) {
            $this->label = $extras['label'];
        }
        if (!empty($extras['plural'])) {
            $this->plural = $extras['plural'];
        }
    }

    public function toArray($use_alias = false)
    {
        $out = [
            'name'        => ($use_alias && !empty($this->alias)) ? $this->alias : $this->name,
            'label'       => $this->label,
            'plural'      => $this->plural,
            'description' => $this->description,
        ];

        if ($use_alias) {
            $out['alias'] = $this->alias;
        }

        return $out;
    }

    // Utility methods, remove when this code is reworked, or make it dependent on php-utils

    public static function camelize($string, $separator = null, $preserveWhiteSpace = false, $isKey = false)
    {
        empty($separator) && $separator = ['_', '-'];

        $newString = ucwords(str_replace($separator, ' ', $string));

        if (false !== $isKey) {
            $newString = lcfirst($newString);
        }

        return (false === $preserveWhiteSpace ? str_replace(' ', null, $newString) : $newString);
    }

    /**
     * Converts a word to its plural form. Totally swiped from Yii
     *
     * @param string $name the word to be pluralized
     *
     * @return string the pluralized word
     */
    public static function pluralize($name)
    {
        /** @noinspection SpellCheckingInspection */
        static $blacklist = [
            'Amoyese',
            'bison',
            'Borghese',
            'bream',
            'breeches',
            'britches',
            'buffalo',
            'cantus',
            'carp',
            'chassis',
            'clippers',
            'cod',
            'coitus',
            'Congoese',
            'contretemps',
            'corps',
            'debris',
            'deer',
            'diabetes',
            'djinn',
            'eland',
            'elk',
            'equipment',
            'Faroese',
            'flounder',
            'Foochowese',
            'gallows',
            'Genevese',
            'geese',
            'Genoese',
            'Gilbertese',
            'graffiti',
            'headquarters',
            'herpes',
            'hijinks',
            'Hottentotese',
            'information',
            'innings',
            'jackanapes',
            'Kiplingese',
            'Kongoese',
            'Lucchese',
            'mackerel',
            'Maltese',
            '.*?media',
            'metadata',
            'mews',
            'moose',
            'mumps',
            'Nankingese',
            'news',
            'nexus',
            'Niasese',
            'Pekingese',
            'Piedmontese',
            'pincers',
            'Pistoiese',
            'pliers',
            'Portuguese',
            'proceedings',
            'rabies',
            'rice',
            'rhinoceros',
            'salmon',
            'Sarawakese',
            'scissors',
            'sea[- ]bass',
            'series',
            'Shavese',
            'shears',
            'siemens',
            'species',
            'swine',
            'testes',
            'trousers',
            'trout',
            'tuna',
            'Vermontese',
            'Wenchowese',
            'whiting',
            'wildebeest',
            'Yengeese',
        ];
        /** @noinspection SpellCheckingInspection */
        static $rules = [
            '/(s)tatus$/i'                                                                 => '\1\2tatuses',
            '/(quiz)$/i'                                                                   => '\1zes',
            '/^(ox)$/i'                                                                    => '\1en',
            '/(matr|vert|ind)(ix|ex)$/i'                                                   => '\1ices',
            '/([m|l])ouse$/i'                                                              => '\1ice',
            '/(x|ch|ss|sh|us|as|is|os)$/i'                                                 => '\1es',
            '/(shea|lea|loa|thie)f$/i'                                                     => '\1ves',
            '/(buffal|tomat|potat|ech|her|vet)o$/i'                                        => '\1oes',
            '/([^aeiouy]|qu)ies$/i'                                                        => '\1y',
            '/([^aeiouy]|qu)y$/i'                                                          => '\1ies',
            '/(?:([^f])fe|([lre])f)$/i'                                                    => '\1\2ves',
            '/([ti])um$/i'                                                                 => '\1a',
            '/sis$/i'                                                                      => 'ses',
            '/move$/i'                                                                     => 'moves',
            '/foot$/i'                                                                     => 'feet',
            '/human$/i'                                                                    => 'humans',
            '/tooth$/i'                                                                    => 'teeth',
            '/(bu)s$/i'                                                                    => '\1ses',
            '/(hive)$/i'                                                                   => '\1s',
            '/(p)erson$/i'                                                                 => '\1eople',
            '/(m)an$/i'                                                                    => '\1en',
            '/(c)hild$/i'                                                                  => '\1hildren',
            '/(alumn|bacill|cact|foc|fung|nucle|octop|radi|stimul|syllab|termin|vir)us$/i' => '\1i',
            '/us$/i'                                                                       => 'uses',
            '/(alias)$/i'                                                                  => '\1es',
            '/(ax|cris|test)is$/i'                                                         => '\1es',
            '/s$/'                                                                         => 's',
            '/$/'                                                                          => 's',
        ];

        if (empty($name) || in_array(strtolower($name), $blacklist)) {
            return $name;
        }

        foreach ($rules as $rule => $replacement) {
            if (preg_match($rule, $name)) {
                return preg_replace($rule, $replacement, $name);
            }
        }

        return $name;
    }
}
