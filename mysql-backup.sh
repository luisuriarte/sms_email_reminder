#!/bin/bash
#
####################################################################
##   Shell script to backup all MySql database with single User
##   MySQL Database Backup Script 
##   Written By: Amol Jhod
##   URL: https://www.itsupportwale.com/blog/learn-how-to-backup-up-all-mysql-databases-using-a-single-user-with-a-simple-bash-script
##   Last Update: Apr 25, 2019
##     
##   For more scripts please visit : www.itsupportwale.com
## 
#####################################################################
#####################################################################
#### Caution : This script is takes backup of all databases #########
#############   on which the given user is having access. ###########
######## And Delete the backups older then BAKUP_RETAIN_DAYS ########
#####################################################################
#####################################################################
########### You Have to Update the Below Values #####################
#####################################################################
#
#
BKP_USER="root"     # Enter the username for backup
BKP_PASS="YourPass"       	# Enter the password of the backup user 
#
BKP_DEST="/backup/mysql"			# Enter the Backup directory,change this if you have someother location
#
## Note: Scripts will delete all backup which are older then BACKUP_RETAIN_DAYS##
#
BACKUP_RETAIN_DAYS="10"		# Enter how many days backup you want,
#
########### Use This for only local server #############################
MYSQL_HOST="localhost"
#
#
########################################################################
########### Thats Enough!! NO NEED TO CHANGE THE BELOW VALUES ##########
########################################################################
#
##################### Get Backup DATE ##################################
#
BKP_DAY="$(date +"%Y-%m-%d")"
BKP_DATE="$(date +"%Y-%m-%d_%H-%M-%S")"
#
########## Ignore these default databases shen taking backup ############
#
IGNORE_DB="information_schema performance_schema sys"
#
########## Creating backup dir if not exist #############################
#
[ ! -d $BKP_DEST ] && mkdir -p $BKP_DEST || :
#
################# Autodetect the linux bin path #########################
MYSQL="$(which mysql)"
MYSQLDUMP="$(which mysqldump)"
GZIP="$(which gzip)"
#
###################### Get database list ################################
#
DB_LIST="$($MYSQL -u $BKP_USER -h $MYSQL_HOST -p$BKP_PASS -Bse 'show databases')"
#
{ mkdir ${BKP_DEST}/${BKP_DAY}
    echo "============== ${BKP_DATE} ===============" >> ${BKP_DEST}/Backup-Report.txt
} || {
    echo "No se puede crear el directorio"
    echo "Posiblemente este mal el camino"
}
{ if ! mysql -u ${BKP_USER} -p${BKP_PASS} -e 'exit'; then
    echo '¡Fallo! creo que esta mal la Contraseña o Usuario ' >> ${DB_BACKUP_PATH}/Backup-Report.txt
    exit 1
fi
# mkdir ${BKP_DEST}/${BKP_DAY}
for db in $DB_LIST
do
    skipdb=-1
    if [ "$IGNORE_DB" != "" ];
    then
	for i in $IGNORE_DB
	do
	    [ "$db" == "$i" ] && skipdb=1 || :
	done
    fi
 
    if [ "$skipdb" == "-1" ] ; then
	BKP_DATE="$(date +"%Y-%m-%d_%H-%M-%S")"
	if ! mysql -u ${BKP_USER} -p${BKP_PASS} -e "use "${db}; then
            echo "¡Fallo! Base ${db} No esta en ${BKP_DATE}" >> ${BKP_DEST}/Backup-Report.txt
	else
#
################ Using MYSQLDUMP for bakup and Gzip for compression ###################
#
		BKP_DATE="$(date +"%Y-%m-%d_%H-%M-%S")"
		$MYSQLDUMP -h ${MYSQL_HOST} -u ${BKP_USER} -p${BKP_PASS} --databases ${db} | gzip > ${BKP_DEST}/${BKP_DAY}/${db}-${BKP_DATE}.sql.gz
		
		if [ $? -eq 0 ]; then
				BKP_DATE="$(date +"%Y-%m-%d_%H-%M-%S")"
                touch ${BKP_DEST}/Backup-Report.txt
                echo "Copia completa de ${db} el ${BKP_DATE}" >> ${BKP_DEST}/Backup-Report.txt
                # echo "Copia completa de la base"

            else
				BKP_DATE="$(date +"%Y-%m-%d_%H-%M-%S")"
                touch ${BKP_DEST}/Backup-Report.txt
                echo "Error en copia de ${db} el ${BKP_DATE}" >> ${BKP_DEST}/Backup-Report.txt
                # echo "Error durante la copia"
                exit 1
			fi
		fi
	fi
done
echo "" >> ${BKP_DEST}/Backup-Report.txt
} || {
    echo "Error en la copia"
	BKP_DATE="$(date +"%Y-%m-%d_%H-%M-%S")"
    echo "Fallo en la copia el ${BKP_DATE}" >> ${BKP_DEST}/Backup-Report.txt
    # ./myshellsc.sh 2> ${BKP_DEST}/Backup-Report.txt
}
##### Remove backups older than {BACKUP_RETAIN_DAYS} days  #####

DBDELDATE=`date +"%d%b%Y" --date="${BACKUP_RETAIN_DAYS} days ago"`

if [ ! -z ${BKP_DEST} ]; then
      cd ${BKP_DEST}
      if [ ! -z ${DBDELDATE} ] && [ -d ${DBDELDATE} ]; then
            rm -rf ${DBDELDATE}
      fi
fi

### End of script ####