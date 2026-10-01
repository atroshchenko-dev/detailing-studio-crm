<?php

declare(strict_types=1);

namespace App;

/**
 * Input Validator Class
 */

class Validator
{

    private $errors = [];
    private $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function required($field, $label)
    {
        if (!isset($this->data[$field]) || trim($this->data[$field]) === '') {
            $this->errors[$field] = "Поле \"$label\" обов'язкове для заповнення.";
        }
        return $this;
    }

    public function email($field, $label)
    {
        if (!empty($this->data[$field]) && !filter_var($this->data[$field], FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = "Поле \"$label\" має містити коректний email.";
        }
        return $this;
    }

    public function numeric($field, $label)
    {
        if (!empty($this->data[$field]) && !is_numeric($this->data[$field])) {
            $this->errors[$field] = "Поле \"$label\" має бути числом.";
        }
        return $this;
    }

    public function min($field, $limit, $label)
    {
        if (!empty($this->data[$field]) && mb_strlen($this->data[$field]) < $limit) {
            $this->errors[$field] = "Поле \"$label\" має містити мінімум $limit символів.";
        }
        return $this;
    }

    public function minLength($field, $label, $limit)
    {
        return $this->min($field, $limit, $label);
    }

    public function max($field, $limit, $label)
    {
        if (!empty($this->data[$field]) && mb_strlen($this->data[$field]) > $limit) {
            $this->errors[$field] = "Поле \"$label\" має містити максимум $limit символів.";
        }
        return $this;
    }

    /**
     * Check uniqueness in database
     */
    public function unique($field, $table, $column, $label, $exceptId = null)
    {
        if (empty($this->data[$field])) return $this;

        // Whitelist table and column names to prevent SQL injection
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table) || !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $column)) {
            throw new \InvalidArgumentException('Invalid table or column name');
        }

        $sql = "SELECT COUNT(*) FROM $table WHERE $column = ?";
        $params = [$this->data[$field]];

        if ($exceptId) {
            $sql .= " AND id != ?";
            $params[] = $exceptId;
        }

        $stmt = db()->prepare($sql);
        $stmt->execute($params);

        if ($stmt->fetchColumn() > 0) {
            $this->errors[$field] = "\"$label\" вже використувується.";
        }
        return $this;
    }

    public function isValid()
    {
        return empty($this->errors);
    }

    public function getErrors()
    {
        return $this->errors;
    }

    public function getFirstError()
    {
        return !empty($this->errors) ? reset($this->errors) : null;
    }
}
