<?php

declare(strict_types=1);

/*
 * This file is part of PHP CS Fixer.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *     Dariusz Rumiński <dariusz.ruminski@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace PhpCsFixer\Tests\Fixer\ClassNotation;

use PhpCsFixer\Tests\Test\AbstractFixerTestCase;

/**
 * @internal
 *
 * @covers \PhpCsFixer\Fixer\ClassNotation\NoUnneededFinalMethodFixer
 *
 * @extends AbstractFixerTestCase<\PhpCsFixer\Fixer\ClassNotation\NoUnneededFinalMethodFixer>
 *
 * @author Filippo Tessarotto <zoeslam@gmail.com>
 *
 * @phpstan-import-type _AutogeneratedInputConfiguration from \PhpCsFixer\Fixer\ClassNotation\NoUnneededFinalMethodFixer
 */
final class NoUnneededFinalMethodFixerTest extends AbstractFixerTestCase
{
    /**
     * @dataProvider provideFixCases
     *
     * @param _AutogeneratedInputConfiguration $configuration
     */
    public function testFix(string $expected, ?string $input = null, array $configuration = []): void
    {
        $this->fixer->configure($configuration);
        $this->doTest($expected, $input);
    }

    /**
     * @return iterable<array{0: string, 1?: null|string, 2?: _AutogeneratedInputConfiguration}>
     */
    public static function provideFixCases(): iterable
    {
        yield 'default' => [
            '<?php
final class Foo {
    public function foo() {}
    protected function bar() {}
    private function baz() {}
}',
            '<?php
final class Foo {
    final public function foo() {}
    final protected function bar() {}
    final private function baz() {}
}',
        ];

        yield 'final-after-visibility' => [
            '<?php
final class Foo {
    public function foo() {}
    protected function bar() {}
    private function baz() {}
}',
            '<?php
final class Foo {
    public final function foo() {}
    protected final function bar() {}
    private final function baz() {}
}',
        ];

        yield 'default-static' => [
            '<?php
final class SomeClass {
    public static function foo() {}
    protected static function bar() {}
    private static function baz() {}
}',
            '<?php
final class SomeClass {
    final public static function foo() {}
    final protected static function bar() {}
    final private static function baz() {}
}',
        ];

        yield 'visibility-then-final-then-static' => [
            '<?php
final class SomeClass {
    public static function foo() {}
    protected static function bar() {}
    private static function baz() {}
}',
            '<?php
final class SomeClass {
    public final static function foo() {}
    protected final static function bar() {}
    private final static function baz() {}
}',
        ];

        yield 'visibility-then-static-then-final' => [
            '<?php
final class SomeClass {
    public static function foo() {}
    protected static function bar() {}
    private static function baz() {}
}',
            '<?php
final class SomeClass {
    public static final function foo() {}
    protected static final function bar() {}
    private static final function baz() {}
}',
        ];

        yield 'static-then-visibility-then-final' => [
            '<?php
final class SomeClass {
    static public function foo() {}
    static protected function bar() {}
    static private function baz() {}
}',
            '<?php
final class SomeClass {
    static public final function foo() {}
    static protected final function bar() {}
    static private final function baz() {}
}',
        ];

        yield 'static-then-final-then-visibility' => [
            '<?php
final class SomeClass {
    static public function foo() {}
    static protected function bar() {}
    static private function baz() {}
}',
            '<?php
final class SomeClass {
    static final public function foo() {}
    static final protected function bar() {}
    static final private function baz() {}
}',
        ];

        yield 'no-visibility' => [
            '<?php
final class Foo {
    function foo() {}
    function bar() {}
    function baz() {}
}',
            '<?php
final class Foo {
    final function foo() {}
    final function bar() {}
    final function baz() {}
}',
        ];

        yield 'no-visibility-final-then-static' => [
            '<?php
final class SomeClass {
    static function foo() {}
    static function bar() {}
    static function baz() {}
}',
            '<?php
final class SomeClass {
    final static function foo() {}
    final static function bar() {}
    final static function baz() {}
}',
        ];

        yield 'no-visibility-static-then-final' => [
            '<?php
final class SomeClass {
    static function foo() {}
    static function bar() {}
    static function baz() {}
}',
            '<?php
final class SomeClass {
    static final function foo() {}
    static final function bar() {}
    static final function baz() {}
}',
        ];

        yield 'private-method' => [
            '<?php
class Foo {
    final function bar0() {}
    final public function bar1() {}
    final protected function bar2() {}
    final static public function bar4() {}
    final public static function bar5() {}

    private function bar31() {}
    private function bar32() {}
}',
            '<?php
class Foo {
    final function bar0() {}
    final public function bar1() {}
    final protected function bar2() {}
    final static public function bar4() {}
    final public static function bar5() {}

    final private function bar31() {}
    private final function bar32() {}
}',
        ];

        yield 'private-method-with-visibility-before-final' => [
            '<?php
class Foo {
    private function bar() {}
}',
            '<?php
class Foo {
    private final function bar() {}
}',
        ];

        yield 'preserve-comment' => [
            '<?php final class Foo { /* comment */public function foo() {} }',
            '<?php final class Foo { final/* comment */public function foo() {} }',
        ];

        yield 'multiple-classes-per-file' => [
            '<?php final class Foo { public function foo() {} } abstract class Bar { final public function bar() {} }',
            '<?php final class Foo { final public function foo() {} } abstract class Bar { final public function bar() {} }',
        ];

        yield 'non-final' => [
            '<php class Foo { final public function foo() {} }',
        ];

        yield 'abstract-class' => [
            '<php abstract class Foo { final public function foo() {} }',
        ];

        yield 'final-method-with-private-attribute' => [
            '<?php abstract class Foo { private static $var; final public function foo() {} }',
        ];

        yield 'trait' => [
            '<php trait Foo { final public function foo() {} }',
        ];

        yield 'do not fix constructors' => [
            '<?php
class Bar
{
    final private function __construct()
    {
    }
}',
        ];

        yield 'anonymous-class-inside' => [
            '<?php
final class Foo
{
    public function foo()
    {
    }

    private function bar()
    {
        new class {
            final public function baz()
            {
            }
        };
    }
}
',
            '<?php
final class Foo
{
    final public function foo()
    {
    }

    private function bar()
    {
        new class {
            final public function baz()
            {
            }
        };
    }
}
',
        ];

        yield 'anonymous-class-inside-with-final-private-method' => [
            '<?php
class Foo
{
    private function bar()
    {
        new class {
            private function qux()
            {
            }
        };
    }
}
',
            '<?php
class Foo
{
    private function bar()
    {
        new class {
            final private function qux()
            {
            }
        };
    }
}
',
        ];

        yield 'final private static' => [
            '<?php
class Foo {
    public function bar(){}

    private static function bar1() {echo 1;}
    private static function bar2() {echo 2;}
    static private function bar3() {echo 3;}
    private static function bar4() {echo 4;}
    static private function bar5() {echo 5;}
    static private function bar6() {echo 6;}
}
',
            '<?php
class Foo {
    public function bar(){}

    private static final function bar1() {echo 1;}
    private final static function bar2() {echo 2;}
    final static private function bar3() {echo 3;}
    final private static function bar4() {echo 4;}
    static final private function bar5() {echo 5;}
    static private final function bar6() {echo 6;}
}
',
        ];

        yield [
            '<?php
abstract class Foo {
    public final function bar1(){ $this->bar3(); }
    private function bar2(){ echo 1; }

    private function bar3(){ echo 2; }
}',
            '<?php
abstract class Foo {
    public final function bar1(){ $this->bar3(); }
    private function bar2(){ echo 1; }

    private final function bar3(){ echo 2; }
}',
        ];

        yield [
            '<?php
final class Foo
{
    private function baz() {}
}

class Bar
{
    final private function bar1() {}
}
',
            '<?php
final class Foo
{
    final private function baz() {}
}

class Bar
{
    final private function bar1() {}
}
',
            ['private_methods' => false],
        ];
    }

    /**
     * @dataProvider provideFix81Cases
     *
     * @requires PHP 8.1
     */
    public function testFix81(string $expected, ?string $input = null): void
    {
        $this->doTest($expected, $input);
    }

    /**
     * @return iterable<array{0: string, 1?: string}>
     */
    public static function provideFix81Cases(): iterable
    {
        yield [
            '<?php
final class Foo81 {
    public readonly string $prop1;
    readonly public string $prop2;
    readonly string $prop3;
}
            ',
        ];

        yield [
            '<?php
class Foo81 {
    public readonly string $prop1;
    readonly public string $prop2;
    readonly string $prop3;
}
            ',
        ];

        yield [
            '<?php
final class Foo81 {
    public function foo81() {}
    protected function bar81() {}
    private function baz81() {}
    public readonly string $prop81;
    final public const Y = "i81";
    final const XY = "i81";
}
            ',
            '<?php
final class Foo81 {
    final public function foo81() {}
    final protected function bar81() {}
    final private function baz81() {}
    public readonly string $prop81;
    final public const Y = "i81";
    final const XY = "i81";
}
            ',
        ];

        yield 'enum' => [
            '<?php

enum Foo: string
{
    case Hearts = "H";

    public function test() {
        echo 123;
    }
}

var_dump(Foo::Spades);',
            '<?php

enum Foo: string
{
    case Hearts = "H";

    final public function test() {
        echo 123;
    }
}

var_dump(Foo::Spades);',
        ];
    }

    /**
     * @dataProvider provideFix82Cases
     *
     * @requires PHP 8.2
     */
    public function testFix82(string $expected, string $input): void
    {
        $this->doTest($expected, $input);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideFix82Cases(): iterable
    {
        yield 'final readonly class - final after visibility method' => [
            '<?php
final readonly class Foo {
    public function foo() {}
    protected function bar() {}
    private function baz() {}
}',
            '<?php
final readonly class Foo {
    public final function foo() {}
    protected final function bar() {}
    private final function baz() {}
}',
        ];

        yield 'readonly comment final class - final before visibility method' => [
            '<?php
readonly /* X */ final class Foo {
    public function foo() {}
    protected function bar() {}
    private function baz() {}
}',
            '<?php
readonly /* X */ final class Foo {
    final public function foo() {}
    final protected function bar() {}
    final private function baz() {}
}',
        ];
    }
}
