<?php

namespace Rubix\ML\Transformers;

use Rubix\ML\DataType;
use Rubix\ML\Datasets\Dataset;
use Rubix\ML\Specifications\SamplesAreCompatibleWithTransformer;
use RuntimeException;
use Stringable;

use function is_null;

/**
 * TF-IDF Transformer
 *
 * Term Frequency - Inverse Document Frequency is a measure of how important
 * a word is to a document. The TF-IDF value increases proportionally with
 * the number of times a word appears in a document and is offset by the
 * frequency of the word in the corpus.
 *
 * > **Note**: This transformer assumes that its input is made up of word
 * frequency vectors such as those created by the Word Count Vectorizer.
 *
 * References:
 * [1] S. Robertson. (2003). Understanding Inverse Document Frequency: On
 * theoretical arguments for IDF.
 *
 * @category    Machine Learning
 * @package     Rubix/ML
 * @author      Andrew DalPino
 */
class BM25Transformer implements Transformer, Stateful, Elastic, Stringable
{
    /**
     * The rate at which the TF values decay.
     * 
     * @var float 
     */
    protected $termFrequencyDecay;

    /**
     * The document frequencies of each word i.e. the number of times a word
     * appeared in a document given the entire corpus.
     *
     * @var int[]|null
     */
    protected $dfs;

    /**
     * The number of tokens fitted so far.
     * 
     * @var int|null
     */
    protected $tokenCount;

    /**
     * The inverse document frequency values for each feature column.
     *
     * @var float[]|null
     */
    protected $idfs;

    /**
     * The number of documents (samples) that have been fitted so far.
     *
     * @var int|null
     */
    protected $n;

    /**
     * The average token count per document.
     * 
     * @var float|null
     */
    protected $averageDocumentLength;

    /**
     * @param int $termFrequencyDecay
     * @throws \InvalidArgumentException
     */
    public function __construct(float $termFrequencyDecay = 0.0)
    {
        if ($termFrequencyDecay < 0.0) {
            throw new InvalidArgumentException('Term frequency decay'
                . " must be greater than 0, $termFrequencyDecay given.");
        }

        $this->termFrequencyDecay = $termFrequencyDecay;
    }

    /**
     * Return the data types that this transformer is compatible with.
     *
     * @return \Rubix\ML\DataType[]
     */
    public function compatibility() : array
    {
        return [
            DataType::continuous(),
        ];
    }

    /**
     * Is the transformer fitted?
     *
     * @return bool
     */
    public function fitted() : bool
    {
        return isset($this->idfs);
    }

    /**
     * Return the document frequencies calculated during fitting.
     *
     * @return int[]|null
     */
    public function dfs() : ?array
    {
        return $this->dfs;
    }

    /**
     * Fit the transformer to a dataset.
     *
     * @param \Rubix\ML\Datasets\Dataset $dataset
     */
    public function fit(Dataset $dataset) : void
    {
        $this->dfs = array_fill(0, $dataset->numColumns(), 1);
        $this->tokenCount = 0;
        $this->n = 1;

        $this->update($dataset);
    }

    /**
     * Update the fitting of the transformer.
     *
     * @param \Rubix\ML\Datasets\Dataset $dataset
     * @throws \InvalidArgumentException
     */
    public function update(Dataset $dataset) : void
    {
        SamplesAreCompatibleWithTransformer::check($dataset, $this);

        if (is_null($this->dfs) or is_null($this->n)) {
            $this->fit($dataset);

            return;
        }

        foreach ($dataset->samples() as $sample) {
            foreach ($sample as $column => $tf) {
                if ($tf > 0) {
                    ++$this->dfs[$column];

                    $this->tokenCount += $tf;
                }
            }
        }

        $this->n += $dataset->numRows();

        $idfs = [];

        foreach ($this->dfs as $df) {
            $idfs[] = 1.0 + log($this->n / $df);
        }

        $this->idfs = $idfs;
        
        $this->averageDocumentLength = $this->tokenCount / $this->n;
    }

    /**
     * Transform the dataset in place.
     *
     * @param array[] $samples
     * @throws \RuntimeException
     */
    public function transform(array &$samples) : void
    {
        if (is_null($this->idfs) or is_null($this->averageDocumentLength)) {
            throw new RuntimeException('Transformer has not been fitted.');
        }

        foreach ($samples as &$sample) {
            if ($this->termFrequencyDecay > 0.0) {
                $delta = array_sum($sample) / $this->averageDocumentLength;

                $delta *= $this->termFrequencyDecay;
            } else {
                $delta = 0.0;
            }

            foreach ($sample as $column => &$tf) {
                if ($tf > 0) {
                    $tf *= $tf / ($tf + $delta);
                    $tf *= $this->idfs[$column];
                }
            }
        }
    }

    /**
     * Return the string representation of the object.
     *
     * @return string
     */
    public function __toString() : string
    {
        return 'BM25 TF-IDF Transformer';
    }
}