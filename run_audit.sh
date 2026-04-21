#!/bin/bash

# Configuration
SONAR_TOKEN="sqp_d32ffca3ee81e96bbbc4cea2aa41cfdc855f7e0f" # Replace with your SonarQube token
PROJECT_KEY="jedu"

GATEWAY_IP=$(ip route | grep default | awk '{print $3}')

echo "Step 1: Running SonarQube Scan..."
# This runs on your CPU
sonar-scanner \
  -Dsonar.host.url=http://$GATEWAY_IP:9000 \
  -Dsonar.sources=. \
  -Dsonar.login=$SONAR_TOKEN \
  -Dsonar.projectKey=$PROJECT_KEY \
  -X

echo "Step 2: Exporting Sonar results..."
# Fetch issues and save to JSON
curl -u $SONAR_TOKEN: "http://$GATEWAY_IP:9000/api/issues/search?componentKeys=$PROJECT_KEY&resolved=false" > sonar_report.json
