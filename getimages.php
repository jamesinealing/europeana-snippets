<?php
header('Content-Type: text/html; charset=utf-8');

/*
 * An example of a simple script to create a csv of basic metadata from Europeana
 * and a zip file of downloaded images from a cursor paginated search.json API call.
 * 
 * Whilst working code, this is intended to simply demonstrate a typical appraoch 
 * and does not include user input, error checking, image sizing etc
 *
 * Note depending on the query this can result in a very large number of very large 
 * files being stored on the server it's running on!
*/

// helper function to handle creation of zip file
function Zip($source, $destination)
{
    if (!extension_loaded('zip') || !file_exists($source)) {
        return false;
    }
    $zip = new ZipArchive();
    if (!$zip->open($destination, ZIPARCHIVE::CREATE)) {
        return false;
    }
    $source = str_replace('\\', '/', realpath($source));
    if (is_dir($source) === true)
    {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source), RecursiveIteratorIterator::SELF_FIRST);
        foreach ($files as $file)
        {
            $file = str_replace('\\', '/', $file);
            // Ignore "." and ".." folders
            if( in_array(substr($file, strrpos($file, '/')+1), array('.', '..')) )
                continue;
            $file = realpath($file);
            if (is_dir($file) === true)
            {
                $zip->addEmptyDir(str_replace($source . '/', '', $file . '/'));
            }
            else if (is_file($file) === true)
            {
                $zip->addFromString(str_replace($source . '/', '', $file), file_get_contents($file));
            }
        }
    }
    else if (is_file($source) === true)
    {
        $zip->addFromString(basename($source), file_get_contents($source));
    }
    return $zip->close();
}

// create a directory to store results in, based on timestamp
$directory = 'downloads/'.time().'/';
mkdir($directory, 0777, true);

// set some starting variables
$apikey='api2demo';
$fetchedrecords=0; // how many records have been fetched so far
$rows=18; // the number of rows from each API response - max 100
$records=''; // we'll set this from the api response
$limit= 100; // change this to limit results rather than get full dataset
$cursor='*'; // starting cursor for pagination of full dataset - see http://labs.europeana.eu/api/search#pagination

// if we're just starting OR we've not fetched all records or reched the manually set limit AND we've got a cursor value for the next page
while ($records=='' OR ($records!=0 && $fetchedrecords<=$records && $fetchedrecords<=$limit) && $cursor!='') {
	// set and fecth the API response
	$apicall='http://www.europeana.eu/api/v2/search.json?wskey='.$apikey.'&query=photograph&qf=gaudin&qf=IMAGE_ASPECTRATIO%3ALandscape&qf=IMAGE_COLOUR%3Atrue&qf=IMAGE_SIZE%3Aextra_large&qf=PROVIDER%3ARijksmuseum&reusability=open&qf=TYPE%3AIMAGE&cursor='.$cursor.'&rows='.$rows.'&profile=rich';

	$json = file_get_contents($apicall);
	$response = json_decode($json);
	//print_r($response);
	// check there are records then loop through each one, extracting metadata
	$records = (isset($response->totalResults) ? $response->totalResults : 0);
	echo $apicall.'<br>$totalResults: '.$response->totalResults.'<br>';
	$fetchedrecords = $fetchedrecords + $rows;
	$htmldata = '<hr>Page '.$fetchedrecords/$rows.'<br>';
	// create the html to display results/progress to the user
	$htmldata .='Fetching records: '.($fetchedrecords-$rows+1).' to '.$fetchedrecords.' of '.$records.'<br>';
	foreach ($response->items as $record) {
		$europeanaID=$record->id;

		// get the image file and save it
		$imageURL=$record->edmIsShownBy[0];
		$filename=str_replace("/","_",$europeanaID).'.jpg';
		// $filename=basename($imageURL); // use this *only* if the dataset has unique filenames
		$output = $directory.'/'.$filename;
		file_put_contents($output, file_get_contents($imageURL));

		// add the record to the csv file
		$csvdata = array($europeanaID,$imageURL,$filename);
		if (file_exists($directory.'data.csv') && !is_writeable($directory.'data.csv')){ 
			echo 'csv file write failed';
			return false;
		}
		if ($datafile = fopen($directory.'data.csv', 'a')){
			fputcsv($datafile, $csvdata);
			fclose($datafile);
		} else { 
			echo 'Failed to write metadata csv file'; 
		}
	// put in a small time delay to be nice to providers' servers
	sleep(1);
	}
	// output the html to screen so user can see what's gong on!
	echo $htmldata; echo '<hr>';
	// get the cursor value from the response in order to fetch the next page of results
	$cursor = urlencode($response->nextCursor);
}
// when finished, zip up everything into one file
Zip($directory, $directory.'/_allfiles.zip');

?>
