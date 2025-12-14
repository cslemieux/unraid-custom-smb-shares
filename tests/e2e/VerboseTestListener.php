<?php

use PHPUnit\Framework\Test;
use PHPUnit\Framework\TestListener;
use PHPUnit\Framework\TestListenerDefaultImplementation;
use PHPUnit\Framework\TestSuite;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Warning;

class VerboseTestListener implements TestListener
{
    use TestListenerDefaultImplementation;
    
    private $testCount = 0;
    private $passCount = 0;
    private $failCount = 0;
    private $errorCount = 0;
    
    public function startTest(Test $test): void
    {
        $this->testCount++;
        $name = $test->getName();
        echo "\n[" . $this->testCount . "] Running: " . $name . " ... ";
    }
    
    public function endTest(Test $test, float $time): void
    {
        echo sprintf("(%.3fs)", $time);
    }
    
    public function addError(Test $test, Throwable $t, float $time): void
    {
        $this->errorCount++;
        echo " \033[31mERROR\033[0m";
        echo "\n    " . get_class($t) . ": " . $t->getMessage();
    }
    
    public function addFailure(Test $test, AssertionFailedError $e, float $time): void
    {
        $this->failCount++;
        echo " \033[31mFAIL\033[0m";
        echo "\n    " . $e->getMessage();
    }
    
    public function addWarning(Test $test, Warning $e, float $time): void
    {
        echo " \033[33mWARN\033[0m";
    }
    
    public function endTestSuite(TestSuite $suite): void
    {
        if ($suite->getName() === 'ComprehensiveUITest') {
            $this->passCount = $this->testCount - $this->failCount - $this->errorCount;
            echo "\n\n";
            echo "========================================\n";
            echo "Results: ";
            echo "\033[32m" . $this->passCount . " passed\033[0m, ";
            echo "\033[31m" . $this->failCount . " failed\033[0m, ";
            echo "\033[31m" . $this->errorCount . " errors\033[0m\n";
            echo "========================================\n";
        }
    }
}
