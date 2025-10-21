ARG composer=latest
ARG php=8

FROM composer:${composer} AS composer-bin

FROM php:${php}-cli-alpine

ENV PATH="/usr/local/go/bin:$PATH"
ENV GOROOT=/usr/local/go
ENV GOTOOLCHAIN=local
ENV GOFLAGS=-mod=mod

COPY --from=composer-bin --link /usr/bin/composer /usr/bin/composer
COPY --from=golang:1-alpine --link /usr/local/go /usr/local/go

COPY go.mod go.sum /tmp-app/
WORKDIR /tmp-app
RUN go mod download && rm -rf /tmp-app

WORKDIR /app

CMD ["go", "test", "./..."]
