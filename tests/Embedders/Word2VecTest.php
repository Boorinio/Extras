<?php

namespace Rubix\ML\Tests\Embedders;

use Rubix\ML\DataType;
use Rubix\ML\Datasets\Unlabeled;
use Rubix\ML\Embedders\Word2Vec;

use PHPUnit\Framework\TestCase;
use InvalidArgumentException;

/**
 * @group Embedders
 * @covers \Rubix\ML\Embedders\Word2Vec
 */
class Word2VecTest extends TestCase
{
    /**
     * The number of samples in the validation set.
     *
     * @var int
     */
    protected const DATASET_SIZE = 2;

    /**
     * Constant used to see the random number generator.
     *
     * @var int
     */
    protected const RANDOM_SEED = 0;

    /**
     * @var \Rubix\ML\Embedders\Word2Vec
     */
    protected $embedder;

    /**
     * @var \Rubix\ML\Datasets\Unlabeled
     */
    protected $sampleDataset;

    /**
     * @before
     */
    protected function setUp() : void
    {
        $this->sampleDataset = new Unlabeled([
            ['the quick brown fox jumped over the lazy dog'],
            ['the quick dog runs fast']
        ]);

        $this->embedder = new Word2Vec('neg', 2, 100, 0, .05, 1000, 1);

        srand(self::RANDOM_SEED);
    }

    /**
     * @test
     */
    public function assertPreConditions() : void
    {
        $this->assertFalse($this->embedder->fitted());
    }

    /**
     * @test
     */
    public function build() : void
    {
        $this->assertInstanceOf(Word2Vec::class, $this->embedder);
    }

    /**
     * @test
     */
    public function badNumDimensions() : void
    {
        $this->expectException(InvalidArgumentException::class);

        new Word2Vec('neg', 2, 0);
    }

    /**
     * @test
     */
    public function compatibility() : void
    {
        $expected = [
            DataType::categorical(),
        ];

        $this->assertEquals($expected, $this->embedder->compatibility());
    }

    /**
     * @test
     */
    public function params() : void
    {
        $expected = [
            'layer' => 'neg',
            'window' => 2,
            'dimensions' => 100,
            'sample_rate' => 0,
            'alpha' => .05,
            'epochs' => 1000,
            'min_count' => 1,
        ];

        $this->assertEquals($expected, $this->embedder->params());
    }

    /**
     * @test
     */
    public function trainPredict() : void
    {
        $this->embedder->fit($this->sampleDataset);

        $this->assertTrue($this->embedder->fitted());

        $mostSimilar = $this->embedder->mostSimilar(['dog']);
        $this->assertArrayHasKey('fast', $mostSimilar);

        $score = $mostSimilar['fast'];
        $this->assertGreaterThanOrEqual(.37, $score);
    }
}
