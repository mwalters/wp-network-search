<?php
if (isset($_GET['p']) && trim($_GET['p']) != '') {
    $currentPage = intval(trim($_GET['p']));
    $nextPage = $currentPage + 1;
    $previousPage = $currentPage - 1;
} else {
    $currentPage = 0;
    $nextPage = 1;
    $previousPage = 0;
}
foreach ($this->searchResults as $result) {
?><div class="prag-search-result-wrap clear"><div class="prag-search-result-thumb"><img src="<?php esc_attr_e($result->thumbnail); ?>" alt=""></div><div class="prag-search-result"><span class="prag-search-result-title"><a href="<?php esc_attr_e($result->permalink); ?>"><?php esc_html_e($result->post_title); ?></a></span><span class="prag-search-result-url"><?php esc_html_e($result->permalink) ?></span><div class="prag-search-result-excerpt"><?php esc_html_e(wp_trim_words($result->post_excerpt, 20)); ?></div></div></div><?php
}
echo '<div class="prag-search-paging-wrap">';
$firstRecord = ($currentPage * $this->recordsPerPage) + 1;
if ($firstRecord == 0) { $firstRecord = 1; }
$lastRecord = (($currentPage * $this->recordsPerPage) + $this->recordsPerPage);
if ($lastRecord >= $this->totalResults) { $lastRecord = $this->totalResults; }
echo '<span class="prag-result-record-count">Displaying results ' . $firstRecord . ' through ' . $lastRecord . ' (out of ' . $this->totalResults . ')</span>';
if ($currentPage > 0) {
?><a class="prag-search-paging previous" href="?q=<?php echo urlencode(urldecode($_GET['q'])); ?>&amp;p=<?php echo $previousPage ?>">Previous Page</a><?php
}
if (count($this->searchResults) >= $this->recordsPerPage) { ?><a class="prag-search-paging next" href="?q=<?php echo urlencode(urldecode($_GET['q'])); ?>&amp;p=<?php echo $nextPage ?>">Next Page</a><?php }
echo '</div>';
