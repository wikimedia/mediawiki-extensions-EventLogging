FROM node:20-bookworm

WORKDIR /opt/eventlogging
COPY . ./
RUN npm install

EXPOSE 8192
CMD [ "npm", "run", "eventgate-devserver" ]
