import Sequelize from "sequelize";
import dbConfig from "../config/database";
import initModels from "../models";

const connection = new Sequelize(dbConfig);

initModels(connection);
