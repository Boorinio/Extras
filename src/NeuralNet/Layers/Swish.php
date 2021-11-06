<?php

namespace Rubix\ML\NeuralNet\Layers;

use Tensor\Matrix;
use Rubix\ML\Deferred;
use Rubix\ML\NeuralNet\Parameter;
use Rubix\ML\NeuralNet\Optimizers\Optimizer;
use Rubix\ML\NeuralNet\Initializers\Constant;
use Rubix\ML\NeuralNet\Initializers\Initializer;
use Rubix\ML\NeuralNet\ActivationFunctions\Sigmoid;
use Rubix\ML\Exceptions\RuntimeException;
use Generator;

/**
 * Swish
 *
 * @category    Machine Learning
 * @package     Rubix/ML
 * @author      Andrew DalPino
 */
class Swish implements Hidden, Parametric
{
    /**
     * The initializer of the beta parameter.
     *
     * @var \Rubix\ML\NeuralNet\Initializers\Initializer
     */
    protected \Rubix\ML\NeuralNet\Initializers\Initializer $initializer;
    
    /**
     * The sigmoid activation function.
     *
     * @var \Rubix\ML\NeuralNet\ActivationFunctions\Sigmoid
     */
    protected \Rubix\ML\NeuralNet\ActivationFunctions\Sigmoid $sigmoid;

    /**
     * The width of the layer.
     *
     * @var int<0,max>|null
     */
    protected ?int $width = null;

    /**
     * The parameterized scaling factors.
     *
     * @var \Rubix\ML\NeuralNet\Parameter|null
     */
    protected ?\Rubix\ML\NeuralNet\Parameter $beta = null;

    /**
     * The memoized input matrix.
     *
     * @var \Tensor\Matrix|null
     */
    protected ?\Tensor\Matrix $input = null;

    /**
     * The memorized activation matrix.
     *
     * @var \Tensor\Matrix|null
     */
    protected ?\Tensor\Matrix $computed = null;

    /**
     * @param \Rubix\ML\NeuralNet\Initializers\Initializer|null $initializer
     */
    public function __construct(?Initializer $initializer = null)
    {
        $this->initializer = $initializer ?? new Constant(1.0);
        $this->sigmoid = new Sigmoid();
    }

    /**
     * Return the width of the layer.
     *
     * @internal
     *
     * @throws \Rubix\ML\Exceptions\RuntimeException
     * @return int<0,max>
     */
    public function width() : int
    {
        if ($this->width === null) {
            throw new RuntimeException('Layer has not been initialized.');
        }

        return $this->width;
    }

    /**
     * Initialize the layer with the fan in from the previous layer and return
     * the fan out for this layer.
     *
     * @internal
     *
     * @param int<0,max> $fanIn
     * @return int<0,max>
     */
    public function initialize(int $fanIn) : int
    {
        $fanOut = $fanIn;

        $beta = $this->initializer->initialize(1, $fanOut)->columnAsVector(0);

        $this->width = $fanOut;
        $this->beta = new Parameter($beta);

        return $fanOut;
    }

    /**
     * Compute a forward pass through the layer.
     *
     * @internal
     *
     * @param \Tensor\Matrix $input
     * @return \Tensor\Matrix
     */
    public function forward(Matrix $input) : Matrix
    {
        $computed =  $this->compute($input);

        $this->input = $input;
        $this->computed = $computed;

        return $computed;
    }

    /**
     * Compute an inferential pass through the layer.
     *
     * @internal
     *
     * @param \Tensor\Matrix $input
     * @return \Tensor\Matrix
     */
    public function infer(Matrix $input) : Matrix
    {
        return $this->compute($input);
    }

    /**
     * Calculate the gradient and update the parameters of the layer.
     *
     * @internal
     *
     * @param \Rubix\ML\Deferred $prevGradient
     * @param \Rubix\ML\NeuralNet\Optimizers\Optimizer $optimizer
     * @throws \Rubix\ML\Exceptions\RuntimeException
     * @return \Rubix\ML\Deferred
     */
    public function back(Deferred $prevGradient, Optimizer $optimizer) : Deferred
    {
        if (!$this->beta) {
            throw new RuntimeException('Layer has not been initialized.');
        }

        if (!$this->input or !$this->computed) {
            throw new RuntimeException('Must perform forward pass'
                . ' before backpropagating.');
        }

        $dOut = $prevGradient();

        $dIn = $this->input;

        $dBeta = $dOut->multiply($dIn)->sum();

        $step = $optimizer->step($this->beta, $dBeta);

        $this->beta->update($step);

        $z = $this->input;
        $computed = $this->computed;

        $this->input = null;
        $this->computed = null;

        return new Deferred([$this, 'gradient'], [$z, $computed, $dOut]);
    }

    /**
     * Calculate the gradient for the previous layer.
     *
     * @internal
     *
     * @param \Tensor\Matrix $z
     * @param \Tensor\Matrix $computed
     * @param \Tensor\Matrix $dOut
     * @return \Tensor\Matrix
     */
    public function gradient($z, $computed, $dOut) : Matrix
    {
        return $this->differentiate($z, $computed)->multiply($dOut);
    }

    /**
     * Return the parameters of the layer.
     *
     * @internal
     *
     * @throws \RuntimeException
     * @return \Generator<\Rubix\ML\NeuralNet\Parameter>
     */
    public function parameters() : Generator
    {
        if (!$this->beta) {
            throw new RuntimeException('Layer has not been initialized.');
        }

        yield 'beta' => $this->beta;
    }

    /**
     * Restore the parameters in the layer from an associative array.
     *
     * @internal
     *
     * @param \Rubix\ML\NeuralNet\Parameter[] $parameters
     */
    public function restore(array $parameters) : void
    {
        $this->beta = $parameters['beta'];
    }

    /**
     * Compute the Swish activation function and return a matrix.
     *
     * @param \Tensor\Matrix $z
     * @throws \Rubix\ML\Exceptions\RuntimeException
     * @return \Tensor\Matrix
     */
    protected function compute(Matrix $z) : Matrix
    {
        if (!$this->beta) {
            throw new RuntimeException('Layer has not been initialized.');
        }

        $zHat = $z->multiply($this->beta->param());

        return $this->sigmoid->compute($zHat)
            ->multiply($z);
    }

    /**
     * Calculate the derivative of the activation function at a given output.
     *
     * @param \Tensor\Matrix $z
     * @param \Tensor\Matrix $computed
     * @throws \Rubix\ML\Exceptions\RuntimeException
     * @return \Tensor\Matrix
     */
    protected function differentiate(Matrix $z, Matrix $computed) : Matrix
    {
        if (!$this->beta) {
            throw new RuntimeException('Layer has not been initialized.');
        }

        $ones = Matrix::ones(...$computed->shape());

        return $computed->divide($z)
            ->multiply($ones->subtract($computed))
            ->add($computed);
    }

    /**
     * Return the string representation of the object.
     *
     * @internal
     *
     * @return string
     */
    public function __toString() : string
    {
        return "Swish (beta initializer: {$this->initializer})";
    }
}
