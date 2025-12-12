# RStudio Server : track user sessions
A python script to keep track of user RStudio sessions from within a 
[rocker](https://rocker-project.org/) based docker container.

The [RStudio Server Open Source edition](https://posit.co/download/rstudio-server/) is brilliant, but comes with all the auditing functions disabled (enabled I understand if you buy a licence for the Workbench version). Fair enough, POSIT devs actually need to eat too.

If you have many users, such as large student cohorts, it can nonetheless be useful to keep track of the rstudio sessions : historical list, and overall session durations and numbers.

This Python script uses the process list `ps aux` inside the docker container to track and record rstudio sessions, storing the data in a sqlite3 database file. Storing this database file in a persistent container volume will ensure the stats don't get reset after a container rebuild. The Python script is deamonized using the container s6 supervision, so it should be pretty robust, and doesn't require any additional packages on a [rocker](https://rocker-project.org/) based RStudio Server container.

Beware that duration times are overestimated with this method, because of the 2 hour timeout before an unused rsession process is stopped. 

I also include a very crude PHP script that displays the data in HTML format. I have this PHP script living on the docker host, served via an independent apache2 server that is also running other stuff. Note that for this to work, the sqlite3 database file needs to be readable from the host system (usually the case if this file is made persistent to container rebuilds by residing on a docker mounted volume).

## In the docker container

Mount some persistent volume, and then either via a `post_start` script in your `docker-compose.yml` or via classic `Dockerfile` commands:

 - Place the included `s6_track_rsessions` directory under `/etc/s6/services`
 - modify the path to the Python script in the `/etc/s6/services/s6_track_rsessions/run` script
 - add a command to deamonize the Python script  `s6-supervise /etc/s6/services/s6_track_rsessions&`
 
 You're done !
 
 Every minute, processes are scanned and stats are updated in the sqlite3 database file.
