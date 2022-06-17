#!/bin/bash
php FDFBulkFormatter.php
cd fdfs
for file in *.fdf; do pdftk conv.pdf fill_form $file output $file.'.fin.pdf'; done
for file in *.fin.pdf; do mv $file finishedPdfs/; done
ls
cd finishedPdfs
for file in *.fin.pdf; do chmod 666 $file; done
ls

