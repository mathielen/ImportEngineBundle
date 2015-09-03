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
        $fieldDefinitions = array();
        foreach ($this->fields as $fieldName=>$fieldGuess) {
            $fieldDefinition = $fieldGuess->guessFieldType($defaultFieldFormat);

            $sanitizedFieldName = self::strtocamelcase($fieldName);
            if ($sanitizedFieldName != $fieldName) {
                $fieldDefinition['serialized_name'] = $fieldName;
                $fieldName = $sanitizedFieldName;
            }

            $fieldDefinitions[$fieldName] = $fieldDefinition;
        }

        return $fieldDefinitions;
    }

    private static function strtocamelcase($str){
        $str = iconv("utf-8","ascii//TRANSLIT", $str);

        return preg_replace_callback('#[^\w]+(.)#',
            create_function('$r', 'return strtoupper($r[1]);'), $str);
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
