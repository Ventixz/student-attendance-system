#!/bin/sh
# Stops the attendance website (Mac / Linux). All attendance data is kept for next time.
cd "$(dirname "$0")" || exit 1
docker compose stop && echo "The attendance website has stopped. Your data is saved. Run  sh start.sh  to start it again."
