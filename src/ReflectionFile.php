``<?php

declare(strict_types=1);

namespace Ensostudio;

use Reflector;
use Reflection;
use ReflectionClass;
use ReflectionType;
use ReflectionNamedType;
use ReflectionFunctionAbstract;
use phpDocumentor\Reflection\DocBlockFactory;
use phpDocumentor\Reflection\DocBlock;

/**
 * The reflection class reports information about a PHP file. Collects functions, interfaces, traits and classes with
 * his constants, properties and methods.
 *
 * @author info@ensostudio.ru
 * @license MIT
 */
class ReflectionFile extends Reflection implements Reflector
{
    /**
     * @var array long/short type names
     */
    protected const FIX_TYPES = [
        'boolean' => 'bool',
        'integer' => 'int'
    ];

    /**
     * @var array existing entities, used to search new entities in the included file
     */
    protected $declared = [
        'constants'  => [],
        'functions'  => [],
        'interfaces' => [],
        'traits'     => [],
        'classes'    => [],
    ];

    /**
     * @var string reflected file
     */
    protected $filename;

    /**
     * @var array PHPDoc comments in file
     */
    protected $comments;

    /**
     * @var array constants in file
     */
    protected $constants;

    /**
     * @var array functions in file
     */
    protected $functions;

    /**
     * @var array interfaces in file
     */
    protected $interfaces;

    /**
     * @var array traits in file
     */
    protected $traits;

    /**
     * @var array classes in file
     */
    protected $classes;

    /**
     * @var DocBlockFactory DocBlock factory
     */
    protected $docBlockFactory;

    /**
     * Creates new reflection instance.
     *
     * @param string $file the path to reflected file
     * @return void
     */
    public function __construct(string $file)
    {
        $this->filename = $file;
        // Store existing entities to detect updates in file.
        foreach ($this->declared as $type => &$values) {
            $values = $this->getDeclared($type);
        }
        /**
         * @todo use `token_get_all()` instead.
         */
        require_once realpath($file);
    }

    /**
     * Exports a reflected file.
     *
     * @param string $file reflected file
     * @param bool $return Return the export, as opposed to emitting it?
     * @return string
     */
    public static function export(string $file, bool $return = false): string
    {
        $reflection = new static($file);
        $result = [];
        $parts = [
            'Constants'  => null,
            'Functions'  => 'ReflectionFunction',
            'Interfaces' => 'ReflectionClass',
            'Traits'     => 'ReflectionClass',
            'Classes'    => 'ReflectionClass',
        ];
        foreach ($parts as $part => $reflectionClass) {
            $entities = call_user_func([$reflection, 'get' . $part]);
            if ($reflectionClass) {
                foreach ($entities as $name => &$info) {
                    $info = call_user_func($reflectionClass . '::export', $name, true);
                }
            }
            $result[] = sprintf(
                '- %s [%d] {%s}',
                $part,
                count($entities),
                PHP_EOL . implode(PHP_EOL, $entities) . PHP_EOL
            );
        }

        $result = sprintf('File [ %s ] {%s}', $file, PHP_EOL . implode(PHP_EOL . PHP_EOL, $result) . PHP_EOL);
        if ($return) {
            return $result;
        }
        echo $result;
        return '';
    }

    /**
     * Returns the string representation of the ReflectionFile object.
     *
     * @return string
     */
    public function __toString(): string
    {
        return static::export($this->filename, true);
    }

    /**
     * Returns declared entities by type.
     *
     * @param string $type declared entities: 'classes', 'functions' and etc
     * @return array
     */
    protected function getDeclared(string $type): array
    {
        $func = 'get_defined_' . $type;
        if (in_array($type, ['constants', 'functions'])) {
            $result = call_user_func($func, true);

            return $result['user'] ?? [];
        }

        return call_user_func($func);
    }

    /**
     * Returns parsed comment or some tag.
     *
     * @param Reflector $reflection reflection instance
     * @param null|string $returnTags returned tag (optional)
     * @return array
     */
    protected function getComment(Reflector $reflection, string $returnTag = null)
    {
        if (property_exists($reflection, 'class')) {
            $key = $reflection->class . '::' . $reflection->name;
        } else {
            $key = $reflection->name;
        }
        if (! isset($this->comments[$key])) {
            if (! $this->docBlockFactory) {
                $this->docBlockFactory = DocBlockFactory::createInstance();
            }
            $docBlock = $this->docBlockFactory->create($reflection->getDocComment());
            $this->comments[$key] = [
                'description' => $docBlock->getDescription()->render(),
                'tags' => $docBlock->getTags()
            ];
        }
        if ($returnTag) {
            $result = [];
            foreach ($this->comments[$key]['tags'] as $tag) {
                if ($tag->getName() == $returnTag) {
                    $result[] = $tag;
                }
            }
            return $result;
        }
        return $this->comments[$key];
    }

    /**
     * Returns modifier names.
     *
     * @param Reflector $reflection reflection instance
     * @return array
     */
    protected function getModifierNames(Reflector $reflection): array
    {
        return Reflection::getModifierNames($reflection->getModifiers());
    }

    /**
     * Returns type name.
     *
     * @param ReflectionType $type
     * @return string
     */
    protected function getTypeName(ReflectionType $type): string
    {
        if ($type instanceof ReflectionNamedType) {
            return $type->getName();
        }

        return (string) $type;
    }

    /**
     * Returns declaring class of method, property or constant.
     *
     * @param Reflector $reflection reflection instance
     * @return string name of declaring class or 'self'
     */
    protected function getDeclaringClass(Reflector $reflection): string
    {
        $declaringClass = $reflection->getDeclaringClass();

        return $declaringClass == $reflection->class ? 'self' : $declaringClass;
    }

    /**
     * Returns constants of class.
     *
     * @param ReflectionClass $class reflection class
     * @return array
     */
    protected function getClassConstants(ReflectionClass $class): array
    {
        $constants = [];
        foreach ($class->getConstants() as $name => $value) {
            $constant = $class->getReflectionConstant($name);
            $constants[$name] = [
                'type' => gettype($value),
                'value' => $value,
                'modifiers' => $this->getModifierNames($constant),
                'description' => $this->getComment($constant),
                'inherited' => $this->getDeclaringClass($constant),
            ];
        }

        return $constants;
    }

    /**
     * Returns properties of class.
     *
     * @param ReflectionClass $class reflection class
     * @return array
     */
    protected function getProperties(ReflectionClass $class): array
    {
        $properties = [];
        foreach ($class->getDefaultProperties() as $name => $value) {
            $property = $class->getProperty($name);
            $description = '';
            $type = '';
            $varTag = $this->getComment($property, 'var');
            if ($varTag) {
                $type = (string) $varTag[0]->getType();
                $description = (string) $varTag[0]->getDescription();
            }
            if (! $type && isset($value)) {
                $type = gettype($value);
            }
            $properties[$name] = [
                'type' => $type,
                'value' => $value,
                'modifiers' => $this->getModifierNames($property),
                'description' => $description,
                'inherited' => $this->getDeclaringClass($property),
            ];
        }

        return $properties;
    }

    /**
     * Returns type of result of function or method.
     *
     * @param ReflectionFunctionAbstract $function reflection function or method
     * @return string
     */
    protected function getFunctionReturn(ReflectionFunctionAbstract $function): string
    {
        if ($function->hasReturnType()) {
            return $this->getTypeName($function->getReturnType());
        }
        $types = [];
        foreach ($this->getComment($property, 'return') as $tag) {
            $types[] = (string) $tag->getType();
        }
        if ($types) {
            return implode('|', $types);
        }
        return 'void';
    }

    /**
     * Returns information about parameters of function or method.
     *
     * @param ReflectionFunctionAbstract $function reflection function or method
     * @return array
     */
    protected function getFunctionParameters(ReflectionFunctionAbstract $function): array
    {
        $params = [];
        $tags = [];
        foreach ($this->getComment($function, 'param') as $tag) {
            $tags[$tag->getVariableName()] = $tag;
        }
        foreach ($function->getParameters() as $param) {
            $name = $param->getName();
            $type = $param->hasType() ? $this->getTypeName($param->getType()) : '';
            $description = '';
            $value = null;
            if ($param->isDefaultValueAvailable()) {
                if ($param->isDefaultValueConstant()) {
                    $value = 'const ' . $param->getDefaultValueConstantName();
                    if (! $type) {
                        $type = gettype(constant($param->getDefaultValueConstantName()));
                    }
                } else {
                    $value = $param->getDefaultValue();
                    if (! $type && isset($value)) {
                        $type = gettype($value);
                    }
                }
            }
            if (isset($tags[$name])) {
                $description = (string) $tags[$name]->getDescription();
                if (! $type) {
                    $type = (string) $tags[$name]->getType();
                }
            }
            $params[$name] = [
                'type' => $type,
                'value' => $value,
                'description' => $description,
                'optional' => $param->isOptional(),
            ];
        }

        return $params;
    }

    /**
     * Returns information about methods of class.
     *
     * @param ReflectionClass $class reflection class
     * @return array
     */
    protected function getMethods(ReflectionClass $class): array
    {
        $methods = [];
        foreach ($class->getMethods() as $name) {
            $method = $class->getMethod($name);
            $methods[$name] = [
                'parameters' => $this->getFunctionParameters(),
                'return' => $this->getFunctionReturn($method),
                'modifiers' => $this->getModifierNames($method),
                'description' => $this->getComment($method),
                'inherited' => $this->getDeclaringClass($method),
            ];
        }

        return $methods;
    }

    /**
     * Returns information about entity.
     *
     * @param string $type type of entity
     * @return array
     */
    protected function get(string $type): array
    {
        if (! is_array($this->{$type})) {
            $this->{$type} = array_diff($this->getDeclared($type), $this->declared[$type]);
            $this->declared[$type] = null;
            foreach ($this->{$type} as $key => $name) {
                $class = new ReflectionClass($name);
                $parentClass = $class->getParentClass();
                $this->{$type}[$key] = [
                    'parent' => $parentClass ? $parentClass->getName() : '',
                    'interfaces' => $class->getInterfaceNames(),
                    'traits' => $class->isInterface() ? [] : $class->getTraitNames(),
                    'modifiers' => $this->getModifierNames($class),
                    'description' => $this->getComment($class),
                    'constants' => $this->getClassConstants($class),
                    'properties' => $class->isInterface() ? [] : $this->getProperties($class),
                    'methods' => $this->getMethods($class),
                ];
            }
        }

        return $this->{$type};
    }

    /**
     * Returns added constants.
     *
     * @return array
     */
    public function getConstants(): array
    {
        return $this->get('constants');
    }

    /**
     * Returns added functions.
     *
     * @return array
     */
    public function getFunctions(): array
    {
        return $this->get('functions');
    }

    /**
     * Returns added interfaces.
     *
     * @return array
     */
    public function getInterfaces(): array
    {
        return $this->get('interfaces');
    }

    /**
     * Returns added traits.
     *
     * @return array
     */
    public function getTraits(): array
    {
        return $this->get('traits');
    }

    /**
     * Returns added classes.
     *
     * @return array
     */
    public function getClasses(): array
    {
        return $this->get('classes');
    }
}
