<?php

namespace Kinikit\Persistence\Database\PreparedStatement;

/**
 * Blob wrapper class which encapsulates blob data.  This may be passed as a parameter argument value to a prepared statement, when we want to insert
 * a particularly large value into a blob column as it permits certain database connections to utilise more efficient techniques to send large values across.
 *
 */
class BlobWrapper {

    private $contentFileName = null;
    private $contentText = null;
    private $filePointer = null;
    private $stringPointer = 0;
    private $chunkSize = null;

    const DEFAULT_CHUNK_SIZE = 8192;


    /**
     * Construct a blob wrapper which acts as a holder for a blob value which may be supplied either as straight text
     * or as a filename to an existing file.
     *
     * @param string $contentText
     * @param string $contentFile
     *
     * @return BlobWrapper
     */
    public function __construct($contentText = null, $contentFileName = null, $chunkSize = self::DEFAULT_CHUNK_SIZE) {
        $this->contentText = $contentText;
        $this->contentFileName = $contentFileName;
        $this->chunkSize = $chunkSize;
    }

    /**
     * Get the content text
     *
     * @return string
     */
    public function getContentText() {
        return $this->contentText;
    }

    /**
     * Get the content file name if supplied
     *
     * @return string
     */
    public function getContentFileName() {
        return $this->contentFileName;
    }

    /**
     * Return the next chunk from this blob wrapper in an iterative manner until completely sent
     *
     */
    public function nextChunk() {


        // If we have content text, deal with this
        if (strlen($this->contentText) > 0) {
            if ($this->stringPointer >= strlen($this->contentText)) {
                $this->stringPointer = 0;
                return null;
            }

            // Derive the next chunk
            $nextChunk = substr($this->contentText, $this->stringPointer, $this->chunkSize);
            $this->stringPointer += $this->chunkSize;
            return $nextChunk;
        } else if (strlen($this->contentFileName) > 0) {

            // If no file pointer active, open one
            if (!$this->filePointer) {
                $this->filePointer = fopen($this->contentFileName, "r");
            }

            // if not end of file, return the next chunk
            if (!feof($this->filePointer)) {
                return fread($this->filePointer, $this->chunkSize);
            } else {
                fclose($this->filePointer);
                $this->filePointer = null;
                return null;
            }

        }

    }

}

?>
