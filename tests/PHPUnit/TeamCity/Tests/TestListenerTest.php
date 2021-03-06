<?php

namespace PHPUnit\TeamCity\Tests;

use AspectMock;
use PHPUnit\TeamCity\TestListener;
use PHPUnit\TeamCity\Tests\Fixtures\DataProviderTest;
use SebastianBergmann\Comparator\ComparisonFailure;

class TestListenerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Listener to test
     *
     * @var TestListener
     */
    private $listener;

    /**
     * Memory stream to write teamcity messages
     * @var resource
     */
    private $out;

    protected function setUp()
    {
        $this->out = fopen('php://memory', 'w');
        $this->listener = new TestListener($this->out);

        // mock standard php functions output
        AspectMock\Test::func('PHPUnit\TeamCity', 'date', '2015-05-28T16:14:12.17+0700');
        AspectMock\Test::func('PHPUnit\TeamCity', 'getmypid', 24107);
    }

    protected function tearDown()
    {
        fclose($this->out);
        $this->listener = null;
        AspectMock\Test::clean();
    }

    public function testStartTest()
    {
        $test = $this->createTestMock('UnitTest');

        $this->listener->startTest($test);
        $expected = <<<EOS
##teamcity[testStarted captureStandardOutput='true' name='UnitTest' timestamp='2015-05-28T16:14:12.17+0700' flowId='24107']

EOS;

        $this->assertOutputSame($expected);
    }

    public function testEndTest()
    {
        $test = $this->createTestMock('UnitTest');

        $time = 5.6712;

        $this->listener->endTest($test, $time);
        $expected = <<<EOS
##teamcity[testFinished duration='5671' name='UnitTest' timestamp='2015-05-28T16:14:12.17+0700' flowId='24107']

EOS;

        $this->assertOutputSame($expected);
    }

    public function testStartTestSuite()
    {
        $testSuite = new \PHPUnit_Framework_TestSuite('TestSuite');

        $this->listener->startTestSuite($testSuite);
        $expected = <<<EOS
##teamcity[testSuiteStarted name='TestSuite' timestamp='2015-05-28T16:14:12.17+0700' flowId='24107']

EOS;

        $this->assertOutputSame($expected);
    }

    public function testEndTestSuite()
    {
        $testSuite = new \PHPUnit_Framework_TestSuite('TestSuite');

        $this->listener->endTestSuite($testSuite);
        $expected = <<<EOS
##teamcity[testSuiteFinished name='TestSuite' timestamp='2015-05-28T16:14:12.17+0700' flowId='24107']

EOS;

        $this->assertOutputSame($expected);
    }

    public function testAddSkippedTest()
    {
        $test = $this->createTestMock('SkippedTest');
        $exception = new \Exception('Skip message');
        $time = 5;

        $this->listener->addSkippedTest($test, $exception, $time);
        $expected = <<<EOS
##teamcity[testIgnored message='Skip message' name='SkippedTest' timestamp='2015-05-28T16:14:12.17+0700' flowId='24107']

EOS;

        $this->assertOutputSame($expected);
    }

    public function testAddIncompleteTest()
    {
        $test = $this->createTestMock('IncompleteTest');
        $exception = new \Exception('Incomplete message');
        $time = 5;

        $this->listener->addIncompleteTest($test, $exception, $time);
        $expected = <<<EOS
##teamcity[testIgnored message='Incomplete message' name='IncompleteTest' timestamp='2015-05-28T16:14:12.17+0700' flowId='24107']

EOS;

        $this->assertOutputSame($expected);
    }

    public function testAddRiskyTest()
    {
        $test = $this->createTestMock('RiskyTest');
        $exception = new \Exception('Ricky message');
        $time = 5;

        $this->listener->addRiskyTest($test, $exception, $time);
        $expected = <<<EOS
##teamcity[testIgnored message='Ricky message' name='RiskyTest' timestamp='2015-05-28T16:14:12.17+0700' flowId='24107']

EOS;

        $this->assertOutputSame($expected);
    }

    public function testAddError()
    {
        $test = $this->createTestMock('UnitTest');
        $exception = new \Exception('ErrorMessage');
        $time = 5;

        $this->listener->addError($test, $exception, $time);

        $expectedOutputStart = <<<EOS
##teamcity[testFailed message='Exception: ErrorMessage' details='
EOS;

        $expectedOutputEnd = <<<EOS
 name='UnitTest' timestamp='2015-05-28T16:14:12.17+0700' flowId='24107']

EOS;

        $this->assertOutputStartsAndEndsWith($expectedOutputStart, $expectedOutputEnd);
    }

    public function testAddFailure()
    {
        $test = $this->createTestMock('FailedTest');
        $exception = new \PHPUnit_Framework_AssertionFailedError('Assertion error');
        $time = 5;

        $this->listener->addFailure($test, $exception, $time);

        $expectedOutputStart = <<<EOS
##teamcity[testFailed message='Assertion error' details='
EOS;

        $expectedOutputEnd = <<<EOS
 name='FailedTest' timestamp='2015-05-28T16:14:12.17+0700' flowId='24107']

EOS;

        $this->assertOutputStartsAndEndsWith($expectedOutputStart, $expectedOutputEnd);
    }

    public function testAddWarningIsCompatibleWithLessThanPHPUnit51Version()
    {
        if (version_compare(\PHPUnit_Runner_Version::id(), '5.0.99', '>')) {
            $this->markTestSkipped();
        }

        $test = $this->createTestMock('FailedTest');
        $exception = new \PHPUnit_Framework_Warning('Assertion warning');
        $time = 5;

        $this->listener->addWarning($test, $exception, $time);

        $expectedOutput = <<<EOS
##teamcity[testFailed message='Assertion warning' name='FailedTest' timestamp='2015-05-28T16:14:12.17+0700' flowId='24107']

EOS;
        $this->assertOutputSame($expectedOutput);
    }

    /**
     * @requires PHPUnit 5.0.99
     */
    public function testAddWarning()
    {
        $test = $this->createTestMock('FailedTest');
        $exception = new \PHPUnit_Framework_Warning('Assertion warning');
        $time = 5;

        $this->listener->addWarning($test, $exception, $time);

        $expectedOutputStart = <<<EOS
##teamcity[testFailed message='Assertion warning' details='
EOS;
        $expectedOutputEnd = <<<EOE
name='FailedTest' timestamp='2015-05-28T16:14:12.17+0700' flowId='24107']

EOE;
        $this->assertOutputStartsAndEndsWith($expectedOutputStart, $expectedOutputEnd);
    }

    public function testAddFailureWithComparisonFailure()
    {
        /* @var \PHPUnit_Framework_TestCase $testCaseMock */
        $testCaseMock = $this->getMockForAbstractClass('\PHPUnit_Framework_TestCase', array('testMethod'), 'TestCase');

        $test = $this->createTestMock('FailedTest');

        $expected = 'expected';
        $actual = 'actual';
        $expectedAsString = 'expectedAsString';
        $actualAsString = 'actualAsString';

        // PHPUnit versions <4.1 ComparisonFailure was part of phpunit
        if (class_exists('PHPUnit_Framework_ComparisonFailure')) {
            $comparisonFailure = new \PHPUnit_Framework_ComparisonFailure(
                $expected,
                $actual,
                $expectedAsString,
                $actualAsString
            );
        // PHPUnit versions >=4.1 ComparisonFailure was moved to sebastianbergmann/comparator package
        } else {
            $comparisonFailure = new ComparisonFailure(
                $expected,
                $actual,
                $expectedAsString,
                $actualAsString
            );
        }

        $exception = new \PHPUnit_Framework_ExpectationFailedException('ExpectationFailed', $comparisonFailure);
        $result = new \PHPUnit_Framework_TestResult();
        $result->addFailure($test, $exception, 2);
        $result->addFailure($test, $exception, 3);

        $testCaseMock->setTestResultObject($result);

        $time = 5;

        $this->listener->addFailure($testCaseMock, $exception, $time);

        $expectedOutputStart = <<<EOS
##teamcity[testFailed message='ExpectationFailed|n--- Expected|n+++ Actual|n@@ @@|n-expectedAsString|n+actualAsString' details=
EOS;

        $expectedOutputEnd = <<<EOS
 type='comparisonFailure' expected='expectedAsString' actual='actualAsString' name='testMethod' timestamp='2015-05-28T16:14:12.17+0700' flowId='24107']

EOS;

        $this->assertOutputStartsAndEndsWith($expectedOutputStart, $expectedOutputEnd);
    }

    public function testTrailingSpacesAreRemovedFromMessage()
    {
        $exception = new \RuntimeException("\n\nError\nwith newlines\n");

        $test = $this->createTestMock('ErrorTest');
        
        $this->listener->addError($test, $exception, 5);

        $expectedOutputStart = <<<EOS
##teamcity[testFailed message='RuntimeException: |n|nError|nwith newlines' details='
EOS;
        $expectedOutputEnd = <<<EOE
' name='ErrorTest' timestamp='2015-05-28T16:14:12.17+0700' flowId='24107']

EOE;

        $this->assertOutputStartsAndEndsWith($expectedOutputStart, $expectedOutputEnd);
    }

    public function testMessageNameForTestWithDataProvider()
    {
        $theClass = new \ReflectionClass('\PHPUnit\TeamCity\Tests\Fixtures\DataProviderTest');
        $testSuite = new \PHPUnit_Framework_TestSuite($theClass);

        $tests = $testSuite->tests();

        $this->assertArrayHasKey(0, $tests);
        $this->assertInstanceOf('PHPUnit_Framework_TestSuite_DataProvider', $tests[0]);
        /* @var \PHPUnit_Framework_TestSuite_DataProvider $dataProviderTestSuite */
        $dataProviderTestSuite = $tests[0];

        $this->assertArrayHasKey(1, $tests);
        $this->assertInstanceOf('\PHPUnit\TeamCity\Tests\Fixtures\DataProviderTest', $tests[1]);
        /* @var DataProviderTest $simpleMethodTest*/
        $simpleMethodTest = $tests[1];

        $this->listener->startTestSuite($testSuite);
        $this->listener->startTestSuite($dataProviderTestSuite);

        foreach ($dataProviderTestSuite as $test) {
            $this->listener->startTest($test);
            $this->listener->endTest($test, 5);
        }

        $this->listener->endTestSuite($dataProviderTestSuite);

        $this->listener->startTest($simpleMethodTest);
        $this->listener->endTest($simpleMethodTest, 6);

        $this->listener->endTestSuite($testSuite);

        $expectedOutput = <<<EOS
##teamcity[testSuiteStarted name='PHPUnit\TeamCity\Tests\Fixtures\DataProviderTest' timestamp='2015-05-28T16:14:12.17+0700' flowId='24107']
##teamcity[testSuiteStarted name='PHPUnit\TeamCity\Tests\Fixtures\DataProviderTest::testMethodWithDataProvider' timestamp='2015-05-28T16:14:12.17+0700' flowId='24107']
##teamcity[testStarted captureStandardOutput='true' name='testMethodWithDataProvider with data set "one"' timestamp='2015-05-28T16:14:12.17+0700' flowId='24107']
##teamcity[testFinished duration='5000' name='testMethodWithDataProvider with data set "one"' timestamp='2015-05-28T16:14:12.17+0700' flowId='24107']
##teamcity[testStarted captureStandardOutput='true' name='testMethodWithDataProvider with data set "two"' timestamp='2015-05-28T16:14:12.17+0700' flowId='24107']
##teamcity[testFinished duration='5000' name='testMethodWithDataProvider with data set "two"' timestamp='2015-05-28T16:14:12.17+0700' flowId='24107']
##teamcity[testStarted captureStandardOutput='true' name='testMethodWithDataProvider with data set "three"' timestamp='2015-05-28T16:14:12.17+0700' flowId='24107']
##teamcity[testFinished duration='5000' name='testMethodWithDataProvider with data set "three"' timestamp='2015-05-28T16:14:12.17+0700' flowId='24107']
##teamcity[testStarted captureStandardOutput='true' name='testMethodWithDataProvider with data set "four"' timestamp='2015-05-28T16:14:12.17+0700' flowId='24107']
##teamcity[testFinished duration='5000' name='testMethodWithDataProvider with data set "four"' timestamp='2015-05-28T16:14:12.17+0700' flowId='24107']
##teamcity[testStarted captureStandardOutput='true' name='testMethodWithDataProvider with data set "five.one"' timestamp='2015-05-28T16:14:12.17+0700' flowId='24107']
##teamcity[testFinished duration='5000' name='testMethodWithDataProvider with data set "five.one"' timestamp='2015-05-28T16:14:12.17+0700' flowId='24107']
##teamcity[testSuiteFinished name='PHPUnit\TeamCity\Tests\Fixtures\DataProviderTest::testMethodWithDataProvider' timestamp='2015-05-28T16:14:12.17+0700' flowId='24107']
##teamcity[testStarted captureStandardOutput='true' name='testSimpleMethod' timestamp='2015-05-28T16:14:12.17+0700' flowId='24107']
##teamcity[testFinished duration='6000' name='testSimpleMethod' timestamp='2015-05-28T16:14:12.17+0700' flowId='24107']
##teamcity[testSuiteFinished name='PHPUnit\TeamCity\Tests\Fixtures\DataProviderTest' timestamp='2015-05-28T16:14:12.17+0700' flowId='24107']

EOS;

        $this->assertOutputSame($expectedOutput);
    }

    public function testMethodNameForSelfDescribingTest()
    {
        $filename = __DIR__ . '/Fixtures/example.phpt';
        $test = new \PHPUnit_Extensions_PhptTestCase($filename);

        $this->listener->startTest($test);

        $expectedOutput = <<<EOS
##teamcity[testStarted captureStandardOutput='true' name='$filename' timestamp='2015-05-28T16:14:12.17+0700' flowId='24107']

EOS;
        $this->assertOutputSame($expectedOutput);
    }

    /**
     * @param string $className
     * @return \PHPUnit_Framework_MockObject_MockObject|\PHPUnit_Framework_Test
     */
    private function createTestMock($className)
    {
        return $this->getMockBuilder('\PHPUnit_Framework_Test')
            ->setMockClassName($className)
            ->getMock();
    }

    /**
     * @param string $expectedOutput Expected output
     * @param string $message Custom assertions message
     */
    private function assertOutputSame($expectedOutput, $message = '')
    {
        $output = $this->readOut();
        $message = $message ?: $output;
        static::assertSame($expectedOutput, $output, $message);
    }

    /**
     * @param string $expectedStart Expected output prefix
     * @param string $expectedEnd Expected output suffix
     * @param string $message Custom assertions message
     */
    private function assertOutputStartsAndEndsWith($expectedStart, $expectedEnd, $message = '')
    {
        $output = $this->readOut();
        $message = $message ?: $output;
        static::assertStringStartsWith($expectedStart, $output, $message);
        static::assertStringEndsWith($expectedEnd, $output, $message);
    }

    /**
     * @return string
     */
    private function readOut()
    {
        return stream_get_contents($this->out, -1, 0);
    }
}
