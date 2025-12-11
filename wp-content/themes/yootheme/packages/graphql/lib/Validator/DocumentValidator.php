<?php declare(strict_types=1);

namespace YOOtheme\GraphQL\Validator;

use YOOtheme\GraphQL\Error\Error;
use YOOtheme\GraphQL\Language\AST\DocumentNode;
use YOOtheme\GraphQL\Language\Visitor;
use YOOtheme\GraphQL\Type\Schema;
use YOOtheme\GraphQL\Utils\TypeInfo;
use YOOtheme\GraphQL\Validator\Rules\DisableIntrospection;
use YOOtheme\GraphQL\Validator\Rules\ExecutableDefinitions;
use YOOtheme\GraphQL\Validator\Rules\FieldsOnCorrectType;
use YOOtheme\GraphQL\Validator\Rules\FragmentsOnCompositeTypes;
use YOOtheme\GraphQL\Validator\Rules\KnownArgumentNames;
use YOOtheme\GraphQL\Validator\Rules\KnownArgumentNamesOnDirectives;
use YOOtheme\GraphQL\Validator\Rules\KnownDirectives;
use YOOtheme\GraphQL\Validator\Rules\KnownFragmentNames;
use YOOtheme\GraphQL\Validator\Rules\KnownTypeNames;
use YOOtheme\GraphQL\Validator\Rules\LoneAnonymousOperation;
use YOOtheme\GraphQL\Validator\Rules\LoneSchemaDefinition;
use YOOtheme\GraphQL\Validator\Rules\NoFragmentCycles;
use YOOtheme\GraphQL\Validator\Rules\NoUndefinedVariables;
use YOOtheme\GraphQL\Validator\Rules\NoUnusedFragments;
use YOOtheme\GraphQL\Validator\Rules\NoUnusedVariables;
use YOOtheme\GraphQL\Validator\Rules\OneOfInputObjectsRule;
use YOOtheme\GraphQL\Validator\Rules\OverlappingFieldsCanBeMerged;
use YOOtheme\GraphQL\Validator\Rules\PossibleFragmentSpreads;
use YOOtheme\GraphQL\Validator\Rules\PossibleTypeExtensions;
use YOOtheme\GraphQL\Validator\Rules\ProvidedRequiredArguments;
use YOOtheme\GraphQL\Validator\Rules\ProvidedRequiredArgumentsOnDirectives;
use YOOtheme\GraphQL\Validator\Rules\QueryComplexity;
use YOOtheme\GraphQL\Validator\Rules\QueryDepth;
use YOOtheme\GraphQL\Validator\Rules\QuerySecurityRule;
use YOOtheme\GraphQL\Validator\Rules\ScalarLeafs;
use YOOtheme\GraphQL\Validator\Rules\SingleFieldSubscription;
use YOOtheme\GraphQL\Validator\Rules\UniqueArgumentDefinitionNames;
use YOOtheme\GraphQL\Validator\Rules\UniqueArgumentNames;
use YOOtheme\GraphQL\Validator\Rules\UniqueDirectiveNames;
use YOOtheme\GraphQL\Validator\Rules\UniqueDirectivesPerLocation;
use YOOtheme\GraphQL\Validator\Rules\UniqueEnumValueNames;
use YOOtheme\GraphQL\Validator\Rules\UniqueFieldDefinitionNames;
use YOOtheme\GraphQL\Validator\Rules\UniqueFragmentNames;
use YOOtheme\GraphQL\Validator\Rules\UniqueInputFieldNames;
use YOOtheme\GraphQL\Validator\Rules\UniqueOperationNames;
use YOOtheme\GraphQL\Validator\Rules\UniqueOperationTypes;
use YOOtheme\GraphQL\Validator\Rules\UniqueTypeNames;
use YOOtheme\GraphQL\Validator\Rules\UniqueVariableNames;
use YOOtheme\GraphQL\Validator\Rules\ValidationRule;
use YOOtheme\GraphQL\Validator\Rules\ValuesOfCorrectType;
use YOOtheme\GraphQL\Validator\Rules\VariablesAreInputTypes;
use YOOtheme\GraphQL\Validator\Rules\VariablesInAllowedPosition;

/**
 * Implements the "Validation" section of the spec.
 *
 * Validation runs synchronously, returning an array of encountered errors, or
 * an empty array if no errors were encountered and the document is valid.
 *
 * A list of specific validation rules may be provided. If not provided, the
 * default list of rules defined by the GraphQL specification will be used.
 *
 * Each validation rule is an instance of GraphQL\Validator\Rules\ValidationRule
 * which returns a visitor (see the [GraphQL\Language\Visitor API](class-reference.md#graphqllanguagevisitor)).
 *
 * Visitor methods are expected to return an instance of [GraphQL\Error\Error](class-reference.md#graphqlerrorerror),
 * or array of such instances when invalid.
 *
 * Optionally a custom TypeInfo instance may be provided. If not provided, one
 * will be created from the provided schema.
 */
class DocumentValidator
{
    /** @var array<string, ValidationRule> */
    private static array $rules = [];

    /** @var array<class-string<ValidationRule>, ValidationRule> */
    private static array $defaultRules;

    /** @var array<class-string<QuerySecurityRule>, QuerySecurityRule> */
    private static array $securityRules;

    /** @var array<class-string<ValidationRule>, ValidationRule> */
    private static array $sdlRules;

    private static bool $initRules = false;

    /**
     * Validate a GraphQL query against a schema.
     *
     * @param array<ValidationRule>|null $rules Defaults to using all available rules
     *
     * @throws \Exception
     *
     * @return list<Error>
     *
     * @api
     */
    public static function validate(
        Schema $schema,
        DocumentNode $ast,
        ?array $rules = null,
        ?TypeInfo $typeInfo = null
    ): array {
        $rules ??= static::allRules();

        if ($rules === []) {
            return [];
        }

        $typeInfo ??= new TypeInfo($schema);

        $context = new QueryValidationContext($schema, $ast, $typeInfo);

        $visitors = [];
        foreach ($rules as $rule) {
            $visitors[] = $rule->getVisitor($context);
        }

        Visitor::visit(
            $ast,
            Visitor::visitWithTypeInfo(
                $typeInfo,
                Visitor::visitInParallel($visitors)
            )
        );

        return $context->getErrors();
    }

    /**
     * Returns all global validation rules.
     *
     * @throws \InvalidArgumentException
     *
     * @return array<string, ValidationRule>
     *
     * @api
     */
    public static function allRules(): array
    {
        if (! self::$initRules) {
            self::$rules = array_merge(
                static::defaultRules(),
                self::securityRules(),
                self::$rules
            );
            self::$initRules = true;
        }

        return self::$rules;
    }

    /** @return array<class-string<ValidationRule>, ValidationRule> */
    public static function defaultRules(): array
    {
        return self::$defaultRules ??= [
            ExecutableDefinitions::class => new ExecutableDefinitions(),
            UniqueOperationNames::class => new UniqueOperationNames(),
            LoneAnonymousOperation::class => new LoneAnonymousOperation(),
            SingleFieldSubscription::class => new SingleFieldSubscription(),
            KnownTypeNames::class => new KnownTypeNames(),
            FragmentsOnCompositeTypes::class => new FragmentsOnCompositeTypes(),
            VariablesAreInputTypes::class => new VariablesAreInputTypes(),
            ScalarLeafs::class => new ScalarLeafs(),
            FieldsOnCorrectType::class => new FieldsOnCorrectType(),
            UniqueFragmentNames::class => new UniqueFragmentNames(),
            KnownFragmentNames::class => new KnownFragmentNames(),
            NoUnusedFragments::class => new NoUnusedFragments(),
            PossibleFragmentSpreads::class => new PossibleFragmentSpreads(),
            NoFragmentCycles::class => new NoFragmentCycles(),
            UniqueVariableNames::class => new UniqueVariableNames(),
            NoUndefinedVariables::class => new NoUndefinedVariables(),
            NoUnusedVariables::class => new NoUnusedVariables(),
            KnownDirectives::class => new KnownDirectives(),
            UniqueDirectivesPerLocation::class => new UniqueDirectivesPerLocation(),
            KnownArgumentNames::class => new KnownArgumentNames(),
            UniqueArgumentNames::class => new UniqueArgumentNames(),
            ValuesOfCorrectType::class => new ValuesOfCorrectType(),
            ProvidedRequiredArguments::class => new ProvidedRequiredArguments(),
            VariablesInAllowedPosition::class => new VariablesInAllowedPosition(),
            OverlappingFieldsCanBeMerged::class => new OverlappingFieldsCanBeMerged(),
            UniqueInputFieldNames::class => new UniqueInputFieldNames(),
            OneOfInputObjectsRule::class => new OneOfInputObjectsRule(),
        ];
    }

    /**
     * @deprecated just add rules via @see DocumentValidator::addRule()
     *
     * @throws \InvalidArgumentException
     *
     * @return array<class-string<QuerySecurityRule>, QuerySecurityRule>
     */
    public static function securityRules(): array
    {
        return self::$securityRules ??= [
            DisableIntrospection::class => new DisableIntrospection(DisableIntrospection::DISABLED),
            QueryDepth::class => new QueryDepth(QueryDepth::DISABLED),
            QueryComplexity::class => new QueryComplexity(QueryComplexity::DISABLED),
        ];
    }

    /** @return array<class-string<ValidationRule>, ValidationRule> */
    public static function sdlRules(): array
    {
        return self::$sdlRules ??= [
            LoneSchemaDefinition::class => new LoneSchemaDefinition(),
            UniqueOperationTypes::class => new UniqueOperationTypes(),
            UniqueTypeNames::class => new UniqueTypeNames(),
            UniqueEnumValueNames::class => new UniqueEnumValueNames(),
            UniqueFieldDefinitionNames::class => new UniqueFieldDefinitionNames(),
            UniqueArgumentDefinitionNames::class => new UniqueArgumentDefinitionNames(),
            UniqueDirectiveNames::class => new UniqueDirectiveNames(),
            KnownTypeNames::class => new KnownTypeNames(),
            KnownDirectives::class => new KnownDirectives(),
            UniqueDirectivesPerLocation::class => new UniqueDirectivesPerLocation(),
            PossibleTypeExtensions::class => new PossibleTypeExtensions(),
            KnownArgumentNamesOnDirectives::class => new KnownArgumentNamesOnDirectives(),
            UniqueArgumentNames::class => new UniqueArgumentNames(),
            UniqueInputFieldNames::class => new UniqueInputFieldNames(),
            ProvidedRequiredArgumentsOnDirectives::class => new ProvidedRequiredArgumentsOnDirectives(),
        ];
    }

    /**
     * Returns global validation rule by name.
     *
     * Standard rules are named by class name, so example usage for such rules:
     *
     * @example DocumentValidator::getRule(GraphQL\Validator\Rules\QueryComplexity::class);
     *
     * @api
     *
     * @throws \InvalidArgumentException
     */
    public static function getRule(string $name): ?ValidationRule
    {
        return static::allRules()[$name] ?? null;
    }

    /**
     * Add rule to list of global validation rules.
     *
     * @api
     */
    public static function addRule(ValidationRule $rule): void
    {
        self::$rules[$rule->getName()] = $rule;
    }

    /**
     * Remove rule from list of global validation rules.
     *
     * @api
     */
    public static function removeRule(ValidationRule $rule): void
    {
        unset(self::$rules[$rule->getName()]);
    }

    /**
     * Validate a GraphQL document defined through schema definition language.
     *
     * @param array<ValidationRule>|null $rules
     *
     * @throws \Exception
     *
     * @return list<Error>
     */
    public static function validateSDL(
        DocumentNode $documentAST,
        ?Schema $schemaToExtend = null,
        ?array $rules = null
    ): array {
        $rules ??= self::sdlRules();

        if ($rules === []) {
            return [];
        }

        $context = new SDLValidationContext($documentAST, $schemaToExtend);

        $visitors = [];
        foreach ($rules as $rule) {
            $visitors[] = $rule->getSDLVisitor($context);
        }

        Visitor::visit(
            $documentAST,
            Visitor::visitInParallel($visitors)
        );

        return $context->getErrors();
    }

    /**
     * @throws \Exception
     * @throws Error
     */
    public static function assertValidSDL(DocumentNode $documentAST): void
    {
        $errors = self::validateSDL($documentAST);
        if ($errors !== []) {
            throw new Error(self::combineErrorMessages($errors));
        }
    }

    /**
     * @throws \Exception
     * @throws Error
     */
    public static function assertValidSDLExtension(DocumentNode $documentAST, Schema $schema): void
    {
        $errors = self::validateSDL($documentAST, $schema);
        if ($errors !== []) {
            throw new Error(self::combineErrorMessages($errors));
        }
    }

    /** @param array<Error> $errors */
    private static function combineErrorMessages(array $errors): string
    {
        $messages = [];
        foreach ($errors as $error) {
            $messages[] = $error->getMessage();
        }

        return implode("\n\n", $messages);
    }
}
