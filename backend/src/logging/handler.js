import logger from "./logger";

export default (log, logLevel) => {
  switch (logLevel) {
    case "debug":
      logger.debug(log);
      break;

    case "debug": {
      logger.debug(log);
      break;
    }

    case "info": {
      logger.info(log);
      break;
    }

    case "warn": {
      logger.warn(log);
      break;
    }

    case "error": {
      logger.error(log);
      break;
    }

    case "fatal": {
      logger.fatal(log);
      break;
    }
  }
};
