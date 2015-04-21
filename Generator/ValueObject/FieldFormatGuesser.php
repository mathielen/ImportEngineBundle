<?php
namespace Mathielen\ImportEngineBundle\Generator\ValueObject;

class FieldFormatGuesser
{

    /**
     * @var FieldFormatGuess[]
     */
    private $fields = array();

    public function getFieldDefinitionGuess($defaultFieldFormat='string')
    {
        $fieldDefinition = array();
        foreach ($this->fields as $fieldName=>$fieldGuess) {
            $fieldDefinition[$fieldName] = $fieldGuess->guessFieldType($defaultFieldFormat);
        }

        return $fieldDefinition;
    }

    public function putRow(array $row)
    {
        foreach ($row as $k=>$v) {
            $this->addGuess($k, $v);
        }
    }

    /**
     * @return FieldFormatGuess
     */
    private function getOrCreateFieldGuess($fieldname)
    {
        $fieldname = strtolower($fieldname);
        if (!isset($this->fields[$fieldname])) {
            $this->fields[$fieldname] = new FieldFormatGuess();
        }

        return $this->fields[$fieldname];
    }

    private function addGuess($fieldname, $fieldvalue)
    {
        $fieldGuess = $this->getOrCreateFieldGuess($fieldname);
        $fieldGuess->addValue($fieldvalue);
    }

}
