<?php
namespace Psalm\Tests;

class ToStringTest extends TestCase
{
    use Traits\InvalidCodeAnalysisTestTrait;
    use Traits\ValidCodeAnalysisTestTrait;

    /**
     * @return iterable<string,array{string,assertions?:array<string,string>,error_levels?:string[]}>
     */
    public function providerValidCodeParse()
    {
        return [
            'validToString' => [
                '<?php
                    class A {
                        function __toString() {
                            return "hello";
                        }
                    }
                    echo (new A);',
            ],
            'inheritedToString' => [
                '<?php
                    class A {
                        function __toString() {
                            return "hello";
                        }
                    }
                    class B {
                        function __toString() {
                            return "goodbye";
                        }
                    }
                    class C extends B {}

                    $c = new C();
                    echo (string) $c;',
            ],
            'goodCast' => [
                '<?php
                    class A {
                        public function __toString(): string
                        {
                            return "hello";
                        }
                    }

                    /** @param string|A $b */
                    function fooFoo($b): void {}

                    /** @param A|string $b */
                    function barBar($b): void {}

                    fooFoo(new A());
                    barBar(new A());',
            ],
            'resourceToString' => [
                '<?php
                    $a = fopen("php://memory", "r");
                    if ($a === false) exit;
                    $b = (string) $a;',
            ],
            'canBeObject' => [
                '<?php
                    class A {
                        public function __toString() {
                            return "A";
                        }
                    }

                    /** @param string|object $s */
                    function foo($s) : void {}

                    foo(new A);',
            ],
            'castArrayKey' => [
                '<?php
                    /**
                     * @param string[] $arr
                     */
                    function foo(array $arr) : void {
                        if (!$arr) {
                            return;
                        }

                        foreach ($arr as $i => $_) {}

                        echo (string) $i;
                    }',
            ],
            'allowToStringAfterMethodExistsCheck' => [
                '<?php
                    function getString(object $value) : ?string {
                        if (method_exists($value, "__toString")) {
                            return (string) $value;
                        }

                        return null;
                    }'
            ],
            'refineToStringType' => [
                '<?php
                    /** @psalm-return non-empty-string */
                    function doesCast() : string {
                        return (string) (new A());
                    }

                    /** @psalm-return non-empty-string */
                    function callsToString() : string {
                        return (new A())->__toString();
                    }

                    class A {
                        /** @psalm-return non-empty-string */
                        function __toString(): string {
                            return "ha";
                        }
                    }'
            ],
        ];
    }

    /**
     * @return iterable<string,array{string,error_message:string,2?:string[],3?:bool,4?:string}>
     */
    public function providerInvalidCodeParse()
    {
        return [
            'echoClass' => [
                '<?php
                    class A {}
                    echo (new A);',
                'error_message' => 'InvalidArgument',
            ],
            'echoCastClass' => [
                '<?php
                    class A {}
                    echo (string)(new A);',
                'error_message' => 'InvalidCast',
            ],
            'invalidToStringReturnType' => [
                '<?php
                    class A {
                        function __toString(): void { }
                    }',
                'error_message' => 'InvalidToString',
            ],
            'invalidInferredToStringReturnType' => [
                '<?php
                    class A {
                        function __toString() { }
                    }',
                'error_message' => 'InvalidToString',
            ],
            'implicitCastWithStrictTypes' => [
                '<?php declare(strict_types=1);
                    class A {
                        public function __toString(): string
                        {
                            return "hello";
                        }
                    }

                    function fooFoo(string $b): void {}
                    fooFoo(new A());',
                'error_message' => 'InvalidArgument',
            ],
            'implicitCastWithStrictTypesToEchoOrSprintf' => [
                '<?php declare(strict_types=1);
                    class A {
                        public function __toString(): string
                        {
                            return "hello";
                        }
                    }

                    echo(new A());
                    sprintf("hello *", new A());',
                'error_message' => 'ImplicitToStringCast',
            ],
            'implicitCast' => [
                '<?php
                    class A {
                        public function __toString(): string
                        {
                            return "hello";
                        }
                    }

                    function fooFoo(string $b): void {}
                    fooFoo(new A());',
                'error_message' => 'ImplicitToStringCast',
            ],
            'implicitCastToUnion' => [
                '<?php
                    class A {
                        public function __toString(): string
                        {
                            return "hello";
                        }
                    }

                    /** @param string|int $b */
                    function fooFoo($b): void {}
                    fooFoo(new A());',
                'error_message' => 'ImplicitToStringCast',
            ],
            'implicitCastFromInterface' => [
                '<?php
                    interface I {
                        public function __toString();
                    }

                    function takesString(string $str): void { }

                    function takesI(I $i): void
                    {
                        takesString($i);
                    }',
                'error_message' => 'ImplicitToStringCast',
            ],
            'implicitConcatenation' => [
                '<?php
                    interface I {
                        public function __toString();
                    }

                    function takesI(I $i): void
                    {
                        $a = $i . "hello";
                    }',
                'error_message' => 'ImplicitToStringCast',
                [],
                true,
            ],
            'resourceCannotBeCoercedToString' => [
                '<?php
                    function takesString(string $s) : void {}
                    $a = fopen("php://memory", "r");
                    takesString($a);',
                'error_message' => 'InvalidArgument',
            ],
            'resourceOrFalseToString' => [
                '<?php
                    $a = fopen("php://memory", "r");
                    if (rand(0, 1)) {
                        $a = [];
                    }
                    $b = (string) $a;',
                'error_message' => 'PossiblyInvalidCast',
            ],
            'cannotCastInsideString' => [
                '<?php
                    class NotStringCastable {}
                    $object = new NotStringCastable();
                    echo "$object";',
                'error_message' => 'InvalidCast',
            ],
            'warnAboutNullableCast' => [
                '<?php
                    class ClassWithToString {
                        public function __toString(): string {
                            return "";
                        }
                    }

                    function maybeShow(?string $message): void {
                        if ($message !== null) {
                            echo $message;
                        }
                    }

                    maybeShow(new ClassWithToString());',
                'error_message' => 'ImplicitToStringCast',
            ],
            'possiblyInvalidCastOnIsSubclassOf' => [
                '<?php
                    class Foo {}

                    /**
                     * @param mixed $a
                     */
                    function bar($a) : ?string {
                        /**
                         * @psalm-suppress MixedArgument
                         */
                        if (is_subclass_of($a, Foo::class)) {
                            return "hello" . $a;
                        }

                        return null;
                    }',
                'error_message' => 'PossiblyInvalidOperand',
            ],
            'allowToStringAfterMethodExistsCheckWithTypo' => [
                '<?php
                    function getString(object $value) : ?string {
                        if (method_exists($value, "__toStrong")) {
                            return (string) $value;
                        }

                        return null;
                    }',
                'error_message' => 'InvalidCast',
            ],
        ];
    }
}
