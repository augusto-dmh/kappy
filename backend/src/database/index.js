import Sequelize from "sequelize";
import dbConfig from "../config/database";
import models from "../models/index";

const connection = new Sequelize(dbConfig);

models.forEach((model) => model.init(connection));
models.forEach(
  (model) => model.associate && model.associate(connection.models)
);

// import Sequelize from 'sequelize';
// import dbConfig from '../config/database';

// const sequelize = new Sequelize(dbConfig);
// export default sequelize;
