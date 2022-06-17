#!/bin/bash
#
# Same as makeFDFs.sh but assumes PHP part already ran
#
cd fdfs
for file in *.fdf; do pdftk "$1" fill_form $file output $file.'.fin.pdf'; done
for file in *.fin.pdf; do mv $file finishedPdfs/; done
ls
cd finishedPdfs
for file in *.fin.pdf; do chmod 666 $file; done
ls

