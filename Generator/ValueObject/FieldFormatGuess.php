<?php
namespace Mathielen\ImportEngineBundle\Generator\ValueObject;

class FieldFormatGuess
{

    private $name;
    private $hasBlankValues = false;

    /**
     * @var array
     */
    private $typeDistribution = array();

    public function __construct($name)
    {
        $this->name = $name;
    }

    private function guessValueType($value)
    {
        if (is_array($value)) {
            return 'array';
        } elseif (is_object($value)) {
            return get_class($value);
        } elseif (is_numeric($value)) {
            if (preg_match('/^[01]+$/', $value)) {
                return 'boolean';
            } elseif (preg_match('/^[0-9]+$/', $value)) {
                return 'integer';
            } else {
                return 'double';
            }
        } else {
            return 'string';
        }
    }

    public function addValue($value)
    {
        if ($value === '') {
            $this->hasBlankValues = true;
        } else {
            $type = $this->guessValueType($value);
            array_key_exists($type, $this->typeDistribution) ? $this->typeDistribution[$type]++ : $this->typeDistribution[$type] = 1;
        }
    }

    public function guessFieldType($defaultFieldFormat)
    {
        $distributionCount = count($this->typeDistribution);

        if ($distributionCount == 1) {
            $types = array_keys($this->typeDistribution);
            $guessedType = $types[0];
        } elseif ($distributionCount == 0) {
            $guessedType = $defaultFieldFormat;
        } else {
            $types = sort($this->typeDistribution);
            $guessedType = $types[0];
        }

        return array(
            'empty' => $this->hasBlankValues,
            'type' => $guessedType
        );
    }

}
